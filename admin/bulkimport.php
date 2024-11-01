<?php

class Hiecor_BulkImport{
	
	private static $instance;
 
    /**
     * Main Instance
     *
     * @staticvar   array   $instance
     * @return      The one true instance
     */
    public static function instance() {
		if (!isset( self::$instance )){
			self::$instance = new self;
		}
        return self::$instance;
    }
	
	public function __construct(){
		add_action('admin_menu', array($this,'bulkimport_menu'));
		add_action('admin_init', array($this,'getProduct'));
	}
	
	public function bulkimport_menu() {

		add_menu_page('HieCOR Payments','HieCOR Payments','manage_options','wc-settings&tab=checkout&section=corcrm_payment','__return_null','',13 ); 
		add_submenu_page('wc-settings&tab=checkout&section=corcrm_payment','Bulk Post','Bulk Post','manage_options','corcrm-bulkimport', array($this,'admin_page'), 'dashicons-upload'); 
	}
	
	function admin_page(){
		?>
		<h1 class="wp-heading-inline">Bulk Push Products to HieCOR</h1>
		
		<h3 style="color: #a70707;">Warning: Please don't refresh the page while Bulk Importing the products to HieCOR.</h3>
		<div id="corcrm-bulkimport">
			<button id="corcrm-bulkimport-btn" class="button button-primary">Bulk Import</button>
			<span class="spinner"></span>
		</div>
		
		<div class="bulkimport-response">
			<ul></ul>
		</div>
		
		<?php
			$this->ajaxscript();
	}
	
	function ajaxscript(){
		
		$limit 		= 1;
		$startfrom 	= (isset($_REQUEST['startfrom']))? intval($_REQUEST['startfrom']) : 1;
		$firstURL 	= add_query_arg(array('corcrm-bulkimport'=>1, 'limit' => $limit,'pagenum' => $startfrom),get_admin_url());
		
		?>
			<script type="text/javascript">
				(function($){
					
					var url = '<?php echo $firstURL; ?>';
					
					$('#corcrm-bulkimport-btn').click(function(){
						bulkimportCorcrm(url);
					});
					
					function bulkimportCorcrm(url){
						$('#corcrm-bulkimport .spinner').addClass('is-active');
						$.get( url, function( data ) {
							$('.bulkimport-response').show();
							console.log(data);
							$('.bulkimport-response > ul').append(data.msg);	
							if(data.msg == 'done'){
								alert('Products Imported!');
								$('#corcrm-bulkimport .spinner').removeClass('is-active');
							}
							else{
								bulkimportCorcrm(data.returnurl);
							}
						},'json');
					}
				}(jQuery));
			</script>
			
			<style>
			.bulkimport-response{background: lightgreen;padding: 10px;border-radius: 3px;max-height: 500px;overflow-y: scroll;display: none;margin-top: 20px;}
			#corcrm-bulkimport .spinner{float: left;}
			</style>
		<?php
	}
	
	
	function getProduct(){
		if(isset($_REQUEST['corcrm-bulkimport'])){
			
			$paged = (isset($_REQUEST['pagenum'])) ? intval($_REQUEST['pagenum']) : 1;
			$limit = (isset($_REQUEST['limit'])) ? intval($_REQUEST['limit']) : 1;
			
				$product = new WP_Query( array('post_type' => 'product', 'posts_per_page' => $limit, 'paged' => $paged,'orderby' => 'ID','order' => 'ASC' ) );
				
				if ( $product->have_posts() ) {
					while ( $product->have_posts() ) {
						$product->the_post();
						$product_id = get_the_ID();
						$product_title = get_the_title();

						$this->pushToCorcrm($product_id);
						$pagenum = $paged+1;
						$returnURL = add_query_arg(array('corcrm-bulkimport' => 1, 'limit' => 1,'pagenum' => $pagenum),get_admin_url());
						$msg = "<li>Product ID:#{$product_id} ({$product_title})</li>";
						$returnData = array('returnurl' => $returnURL,'pid'=>$product_id,'title'=>$product_title,'msg'=>$msg);
					}
					wp_reset_postdata();
				}
				else{
					$returnData = array('msg' => 'done');
				}
			
			
			echo json_encode($returnData);
			die;
		}
	}
	
	function pushToCorcrm($product_id){
		global $crmUtility;
		$crmUtility->push_to_corcrm($product_id);
	}
}

// Call the class and add the menus automatically. 
$BulkImport = Hiecor_BulkImport::instance();