<?php

/*
Plugin Name: External Media Upload
Plugin URI: http://www.ravishakya.com.np/
Version: 0.3
Author: Ravi Shakya
Description: Wordpress External Media Upload will remote grab media from external url link to your server and add them to WordPress media library. 
*/

define( 'ALLOW_UNFILTERED_UPLOADS', true );

class emu {

	function __construct(){

		add_action( 'admin_enqueue_scripts', array( $this , 'emu_wp_admin_style' ) );

		add_action( 'admin_menu', array( $this , 'emu_register_my_custom_submenu_page' ) );

		add_action( 'wp_ajax_save_external_files' , array( $this , 'save_external_files' ) );

		add_action( 'wp_ajax_emu_save_to_post' , array( $this , 'emu_save_to_post' ) );

		add_action( 'wp_ajax_save_emu_settings' , array( $this , 'save_emu_settings' ) );

	}

	/*
	** This is a ajax function to save the Plugins options
	*/



	function save_emu_settings(){


		if( $_POST['action'] == 'save_emu_settings' ){

			$timeout = is_numeric( $_POST['timeout'] ) ? $_POST['timeout'] : 300;

			// Update Timeout

			update_option( 'emu_timeout' , $timeout );

		}

		die;

	}

	/*
	** Enqueue scripts to the external media upload page 
	*/

	function emu_wp_admin_style(){

		if( !empty( $_GET['page'] ) && $_GET['page'] == 'emu' ){

			wp_enqueue_script( 'emu_script', plugin_dir_url( __FILE__ ) . 'js/custom.js' );

			wp_enqueue_style( 'emu_style', plugin_dir_url( __FILE__ ) . 'css/style.css' );

		}

	}

	/*
	** Get the file size
	*/

	function format_size($size) {

	    $sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");

	    if ($size == 0) { return('n/a'); } else {

	    	return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]); 

	    }

	}



	/*

	** Download image from url and insert it to the post. 

	*/



	function emu_save_to_post(){



		if( $_POST['action'] == 'emu_save_to_post' ){



			$url = !empty( $_POST['link'] ) ? sanitize_text_field( $_POST['link'] ) : '';

			$post_id = !empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : '';

			$result = $this->save_external_files( $post_id , $url );

			/*
			** File not found 
			*/

			if( $result['result'] == 'error' ){

				echo json_encode( 
					array( 
						'result' => 'file_not_found' , 
						'message' => $result['message'] 
					) 
				);

				die;

			}



			$data = set_post_thumbnail( $post_id, $result['image_id'] );

			if( $data == false ){

				echo json_encode( array( 'result' => 'error' , 'message' => 'Could not set thumbnail due to an error.' ) );

			} else {

				echo json_encode( array( 'result' => 'success' ) );

			}



			die;



		}



	}



	/*

	** Download external files and upload to our server.

	*/



	function save_external_files( $post_id = 0 , $url = null ){



		$data = array();



		if( $url == null ){

			$url = $_POST['link'];	

		} 

		

		$validLink = $this->checkValidLink( $url );

		//$validLink = true;



		if( $validLink == true ){



			$timeout = get_option( 'emu_timeout' , 300 );

			$tmp = download_url( $url , $timeout );

			$file_array = array();

			$fileextension = image_type_to_extension( exif_imagetype( $url ) );
			$path = pathinfo( $tmp );

			if( ! isset( $path['extension'] ) ){

				 $tmpnew = $tmp . '.tmp';
				 $file_array['tmp_name'] = $tmpnew;				 
				 
			} else {
				$file_array['tmp_name'] = $tmp;
			}	

			$name = pathinfo( $url, PATHINFO_FILENAME )  . $fileextension;
			$file_array['name'] = $name;
			// $file_array['type'] = mime_content_type( $file_array['tmp_name'] );		

			// If error storing temporarily, unlink

			if ( is_wp_error( $tmp ) ) {

				@unlink($file_array['tmp_name']);

				$file_array['tmp_name'] = '';

				$data['result'] = 'error';

				$data['message'] = $tmp->get_error_message();

				$data['file_size'] = '0 KB';

				$data['actions'] = '-';

				// If ajax call

				if( $post_id != 0 ){

					return $data;

				} else {

					echo json_encode( $data );	

				}				

				die;
			}

			// do the validation and storage stuff			

			$id = media_handle_sideload( $file_array, $post_id , $desc = null );

			$local_url = wp_get_attachment_url( $id );

			if( $local_url != false ){

				$fullPath = $local_url;

			} else {

				$fullPath = '#';

			}

			// If error storing permanently, unlink

			if ( is_wp_error($id) ) {

				@unlink($file_array['tmp_name']);

			}

			$file = $this->check_headers( $local_url );

			$data['result'] = 'success';

			$data['image_id'] = $id;

			$data['message'] = 'Uploaded Succesfully';

			$data['file_size'] = $this->format_size( preg_replace( "/[^0-9]/" , "" , $file['size'] ) );

			$data['actions'] = '<a href="' . $fullPath . '" target="blank">View</a> | <a href="' . admin_url() . 'post.php?post=' . $id . '&action=edit' . '" target="blank">Edit</a>';

			

			// If ajax call

			if( $post_id != 0 ){

				return $data;

			} else {

				echo json_encode( $data );	

			}



			die;



		} else {



			$data['result'] = 'error';

			$data['message'] = 'File not found';

			$data['file_size'] = '0 KB';

			$data['actions'] = '-';



			// If ajax call

			if( $post_id != 0 ){

				return $data;

			} else {

				echo json_encode( $data );	

			}

			

			die;



		}



	}



	/*

	** Check Headers

	*/



	function check_headers( $link ){

		$curl = curl_init();

		curl_setopt_array( $curl, array(

		    CURLOPT_HEADER => true,

		    CURLOPT_NOBODY => true,

		    CURLOPT_RETURNTRANSFER => true,

		    CURLOPT_SSL_VERIFYPEER => false,

		    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',

		    CURLOPT_URL => $link ) );

		$file_headers = explode( "\n", curl_exec( $curl ) );
		$size = curl_getinfo( $curl , CURLINFO_CONTENT_LENGTH_DOWNLOAD);

		curl_close( $curl );

		$file_headers['size'] = absint( $size );
		return $file_headers;



	}



	/*

	** Check valid link

	*/



	function checkValidLink( $link ){



		$file_headers = $this->check_headers( $link );

		$headerStatus = trim(preg_replace('/\s\s+/', ' ', $file_headers[0] ));



		$allow_files = array( 'HTTP/1.1 200 OK' , 'HTTP/1.0 200 OK' );



		if( in_array( $headerStatus , $allow_files ) && !empty( $file_headers ) && $file_headers['size'] > 1 ) {

		    return true;

		} else {

		   	return false;

		}		



	}



	/*

	** Add external media upload submenu page

	*/



	function emu_register_my_custom_submenu_page(){



		add_submenu_page( 

	        'upload.php',

	        'External Media Upload',

	        'External Media Upload',

	        'manage_options',

	        'emu',

	        array( $this , 'emu_callback' )

	    );



	}



	/*

	* Get all pages

    */



    function getAllPages(){



    	$args = array(

		   'public'   => true,

		   '_builtin' => false

		);



		$output = 'names'; // names or objects, note names is the default

		$operator = 'and'; // 'and' or 'or'



		$post_types = get_post_types( $args, $output, $operator ); 

		$post_types['post'] = 'post';

		$post_types['page'] = 'page';

		$pageArray = array();



		foreach( $post_types as $post_type ){



			$args = array(

				'post_type' => $post_type,

				'post_status' => 'publish',

				'posts_per_page' => 10

			); 



			if( $post_type == 'page' ){

				$pages = get_pages( $args );

			} else {

				$pages = get_posts( $args );

			}



			if( !empty( $pages ) && is_array( $pages ) ){



				foreach( $pages as $page ){

					$pageArray[$post_type][$page->post_title] = $page->ID;

				}



			} 



		}

		return $pageArray;


    }



    /*

	** Display all the pages on the select field

    */



    function pagesOptions(){



		if( $this->getAllPages() != null ){ 



			foreach( $this->getAllPages() as $key => $pages ){



				echo '<optgroup label="' . $key . '">';



				foreach( $pages as $page => $id ){ ?>

		

					<option value="<?php echo $id; ?>">

						<?php echo $page; ?>

					</option>

		

					<?php 



				}



				echo '</optgroup>';



			}



		} else {

			echo '<option value="">No posts/pages found. Plz create one first.</option>';

		}



    }



    /*

	** Featured image section

    */



	function featuredImage(){ ?>



		<table class="form-table emu-content" id="tab2" style="display:none">

			<tr valign="top" class="featured_image_tr">

				<th colspan="2"><?php _e( 'Set Featured Image' , 'emu' ); ?></th>

			</tr>

			<tr valign="top">

				<th scope="row"><?php _e( 'Add External Image Link' , 'emu' ); ?></th>

				<td>

					<input type="text" class="featured_image_url"/>

					<p class="description">

						<?php _e( 'eg. http://example.com/images/123.jpg' , 'emu' ); ?>

					</p>

				</td>

			</tr>

			<tr valign="top">

				<th scope="row"><?php _e( 'Select Post' , 'emu' ); ?></th>

				<td>

					<select class="post_upload">

						<?php $this->pagesOptions(); ?>

					</select>

				</td>

			</tr>

			<tr valign="top">

				<th></th>

				<td>

					<input type="button" value="Save" class="button-primary button-large button save_to_post"/>

				</td>

			</tr>

			

			<tr valign="top" style="display:none" class="success_row">

				<td colspan="2">

					<div class="save_to_post_success">

						Successfully Uploaded Image.

					</div>

				</td>

			</tr>



			<tr valign="top">

				<td colspan="2">

					<p class="emu_stay_on_page"><?php _e( 'Do not navigate away from this page while upload is in progress.' , 'emu' ); ?></p>

				</td>

			</tr>

			

			<tr valign="top" style="display:none" class="error_row">

				<td colspan="2">

					<div class="save_to_post_error">

					</div>

				</td>

			</tr>



			<tr>

				<td></td>

				<td></td>	

			</tr>



		</table>



		<?php

	}



	/*

	** Allow extensions to upload

	*/



	function emu_allowed_extensions() {



	    $extesions = array(

	        'images' => array(

	        	'ext' => 'jpg, jpeg, gif, png, bmp', 

	        	'label' => __( 'Images', 'emu' )

	        ),

	        'audio' => array(

	        	'ext' => 'mp3, m4a, ogg, wav', 

	        	'label' => __( 'Audio', 'emu' )

	        ),

	        'video' => array(

	        	'ext' => 'mp4, m4v, mov, wmv, avi, mpg, ogv, 3gp, 3g2', 

	        	'label' => __( 'Videos', 'emu' )

	        ),

	        'pdf' => array(

	        	'ext' => 'pdf', 

	        	'label' => __( 'PDF', 'emu' )

	        ),

	        'office' => array(

	        	'ext' => 'doc, docx, ppt, pptx, pps, ppsx, odt, xls, xlsx, psd, txt', 

	        	'label' => __( 'Office Documents', 'emu' )

	        ),

	        'zip' => array(

	        	'ext' => 'zip, rar', 

	        	'label' => __( 'Zip Archives', 'emu' )

	        ),

	        'exe' => array(

	        	'ext' => 'exe', 

	        	'label' => __( 'Executable Files', 'emu' )

	        ),

	        'csv' => array(

	        	'ext' => 'csv', 

	        	'label' => __( 'CSV', 'emu' )

	        )

	    );



	    return apply_filters( 'emu_allowed_extensions', $extesions );

	}



	/*

	** Show all extensions to the user on checkbox

	*/



	function getExtensions( $ext ){



		if( !empty( $ext ) ){



			$db_allow_extensions = get_option( 'emu_allowed_extensions' , array() );


			$arrayExtensions = explode( ',', $ext );



			foreach( $arrayExtensions as $value ){



				$removedSpaceValue = str_replace(' ', '', $value);



				if( in_array( $removedSpaceValue , $db_allow_extensions ) ){

					$checked = 'checked="checked"';

				} else {

					$checked = '';

				}



				echo '<label><input ' . $checked . ' type="checkbox" name="allow_files_extensions" value="' . $value . '"><span>' . $removedSpaceValue . '</span></label>';



			}	



		}		



	}



	/*

	** Show all extension on tr td.

	*/



	function getAllowedExtensions(){



		foreach( $this->emu_allowed_extensions() as $extension ){ ?>



			<tr valign="top">

				<th><?php echo $extension['label'];?></th>

				<td><?php $this->getExtensions( $extension['ext'] ); ?></td>

			</tr>



			<?php

		}



	}



	function options(){ ?>



		<table class="form-table emu-content emu_options" id="tab3" style="display:none">



			<tr valign="top" class="tr_timeout tr_line_br">

				<th><?php _e( 'Timeout' , 'emu' ); ?></th>

				<td>

					<input autocomplete="off" type="text" class="download_timeout" value="<?php echo get_option( 'emu_timeout' , 300 ); ?>">

					<p class="description"><?php _e( 'The timeout for the request to download the file default 300 seconds. If the file is very large in size increase this as we well.' , 'emu' ); ?></p>

				</td>

			</tr>



			<tr>

				<th></th>

				<td>

					<input type="button" value="Save" class="button-primary emu_settings_btn"/>

				</td>

			</tr>



			<tr>

				<td></td>

				<td></td>	

			</tr>



		</table>

		<?php

	}



	function bulkUpload(){ ?>



		<table class="form-table emu-content" id="tab1">



	        <tr valign="top" class="direct_link_tr">

	        	<th scope="row"><?php _e( 'Add Direct Links' , 'emu' ); ?></th>

		        <td>

		        	<textarea rows="10" cols="60" class="files_links"></textarea>

		        	<p class="description">

		        		<?php _e( 'For multiple files put the links in new line. ' , 'emu' ); ?>

		        		<code><?php _e( 'eg. http://example.com/images/123.jpg' , 'emu' ); ?></code>

		        	</p>

		        </td>

	        </tr>

	        <tr>

	        	<td></td>

	        	<td>

	        		<button class="button-primary button-large button save_files"><?php _e( 'Save' , 'emu' ); ?></button>

	        	</td>

	        </tr>



	        <tr valign="top">

				<td colspan="2">

					<p class="emu_stay_on_page"><?php _e( 'Do not navigate away from this page while upload is in progress.' , 'emu' ); ?></p>

				</td>

			</tr>



			<tr>

				<td></td>

				<td></td>	

			</tr>



	    </table>



	    <table class="form-table upload_info" style="display:none;" id="upload_information">

	    	<tr class="table_head">

	    		<td><?php _e( 'No.' , 'emu' ); ?></td>

	    		<td><?php _e( 'Remote Path' , 'emu' ); ?></td>

	    		<td><?php _e( 'Size' , 'emu' ); ?></td>

	    		<td><?php _e( 'Actions' , 'emu' ); ?></td>

	    		<td><?php _e( 'Result' , 'emu' ); ?></td>

	    	</tr>

	    </table>



		<?php

	}



	function emu_callback(){ ?>



		<div class="wrap emu_wrapper">



			<h2><?php _e( 'External Media Upload' , 'emu' ); ?></h2>



			<ul class="emu_tabs">

				<li><a class="active" href="javascript:void(0)" for="tab1"><?php _e( 'Bulk Upload' , 'emu' ); ?></a></li>

				<li><a href="javascript:void(0)" for="tab2"><?php _e( 'Featured Image' , 'emu' ); ?></a></li>

				<li><a href="javascript:void(0)" for="tab3"><?php _e( 'Options' , 'emu' ); ?></a></li>

			</ul>



		    <?php 

		    $this->bulkUpload();

		    $this->featuredImage();

		    $this->options();

		    ?>

			

		</div>



		<script>



			var emuAjax = "<?php echo admin_url( 'admin-ajax.php' ); ?>";

			

		</script>



		<?php

	}



}



$emu = new emu();

// Create a helper function for easy SDK access.
// Create a helper function for easy SDK access.
function emu_fs() {
    global $emu_fs;

    if ( ! isset( $emu_fs ) ) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $emu_fs = fs_dynamic_init( array(
            'id'                  => '1206',
            'slug'                => 'external-media-upload',
            'type'                => 'plugin',
            'public_key'          => 'pk_c4327d9a96b3bd4a28b023c39918b',
            'is_premium'          => false,
            'has_addons'          => false,
            'has_paid_plans'      => false,
            'menu'                => array(
                'slug'           => 'emu',
                'account'        => false,
                'contact'        => false,
                'parent'         => array(
                    'slug' => 'upload.php',
                ),
            ),
        ) );
    }

    return $emu_fs;
}

// Init Freemius.
emu_fs();
// Signal that SDK was initiated.
do_action( 'emu_fs_loaded' );