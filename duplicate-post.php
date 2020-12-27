/*
* Función para duplicar contenidos. Las copias aparecerán como borradores. El usuario será re dirigido a la página de edición.
*/
function rd_duplicate_post_as_draft(){
global $wpdb;
if (! ( isset( $_GET['post']) || isset( $_POST['post']) || ( isset($_REQUEST['action']) && 'rd_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
wp_die('No post to duplicate has been supplied!');
}

/*
* Nonce verification
*/
if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
return;

/*
* obtener el id original del post
*/
$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
/*
* y después todo el contenido original del post
*/
$post = get_post( $post_id );

/*
* el usuario que duplica será el autor del nuevo post,
* si no quieres que sea así, cambia las siguientes líneas por esto: $new_post_author = $post->post_author;
*/
$current_user = wp_get_current_user();
$new_post_author = $current_user->ID;

/*
* si existe la información del post, crear el duplicado
*/
if (isset( $post ) && $post != null) {

/*
* array de los nuevos datos del post
*/
$args = array(
'comment_status' => $post->comment_status,
'ping_status' => $post->ping_status,
'post_author' => $new_post_author,
'post_content' => $post->post_content,
'post_excerpt' => $post->post_excerpt,
'post_name' => $post->post_name,
'post_parent' => $post->post_parent,
'post_password' => $post->post_password,
'post_status' => 'draft',
'post_title' => $post->post_title,
'post_type' => $post->post_type,
'to_ping' => $post->to_ping,
'menu_order' => $post->menu_order
);

/*
* insertar el post por la función wp_insert_post()
*/
$new_post_id = wp_insert_post( $args );

/*
* devolver todos los términos y añadirlos al nuevo borrador del post
*/
$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
foreach ($taxonomies as $taxonomy) {
$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
}

/*
* duplicar toda la meta ingo del post
*/
$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
if (count($post_meta_infos)!=0) {
$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
foreach ($post_meta_infos as $meta_info) {
$meta_key = $meta_info->meta_key;
if( $meta_key == '_wp_old_slug' ) continue;
$meta_value = addslashes($meta_info->meta_value);
$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
}
$sql_query.= implode(" UNION ALL ", $sql_query_sel);
$wpdb->query($sql_query);
}

/*
* por último, redirigir a la pantalla de edición del borrador nuevo
*/
wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
exit;
} else {
wp_die('Post creation failed, could not find original post: ' . $post_id);
}
}
add_action( 'admin_action_rd_duplicate_post_as_draft', 'rd_duplicate_post_as_draft' );

/*
* Añadir el enlace para duplicar posts con post_row_actions
*/
function rd_duplicate_post_link( $actions, $post ) {
if (current_user_can('edit_posts')) {
$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=rd_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicar este elemento" rel="permalink">Duplicar</a>';
}
return $actions;
}

add_filter( 'post_row_actions', 'rd_duplicate_post_link', 10, 2 );

add_filter('page_row_actions', 'rd_duplicate_post_link', 10, 2);
