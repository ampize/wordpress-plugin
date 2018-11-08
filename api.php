<?php
/*
Plugin Name: AMPize.me
Plugin URI: http://www.ampize.me/wordpress-plugin/
Description: AMPize.me plugin for wordpress
Author: WebTales
Version: 0.1
Author URI: http://www.ampize.me
*/

require_once plugin_dir_path( __FILE__ ) . 'includes/UrlToQuery.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/UrlToQueryItem.php';
/*
function add_amp_link() {
    ?>
        <link rel="amphtml" href="https://www.example.com/url/to/amp/document.html">
    <?php
}
add_action("wp_head", "add_amp_link");
*/
function get_site() {
  if ( is_multisite() ) {
    return "Multisite installations are not supported in this plugin version";
  }
  $results = [
    "id" => "1",
    "name" => get_bloginfo("name"),
    "url" => get_bloginfo("url"),
    "description" => get_bloginfo("description"),
    "language" => get_bloginfo("language"),
    "timeZone" => get_timezone(),
    "dateFormat" => get_option("date_format"),
    "adminEmail"=> get_option("admin_email"),
    "iconUrl" => get_site_icon_url()
  ];
  $custom_logo_id = get_theme_mod( 'custom_logo' );
  if (!empty($custom_logo_id)) {
    $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
    $results["logoUrl"] = $image[0];
  }
  return $results;
}

function get_timezone() {
  $timezone_string = get_option( "timezone_string" );

  if ( ! empty( $timezone_string ) ) {
      return $timezone_string;
  }
  $offset  = get_option( "gmt_offset" );
  $hours   = (int) $offset;
  $minutes = ( $offset - floor( $offset ) ) * 60;
  $offset  = sprintf( "%+03d:%02d", $hours, $minutes );
  return $offset;
}

function get_items( $request ) {
    $parameters = $request->get_query_params();
    if (isset($parameters["route"])) {
      $resolver = new UrlToQuery();
      $args = $resolver->resolve($parameters["route"]);
      $page = (isset($parameters["page"])) ? $parameters["page"] : null;
      if ($page) $args["paged"] = intval($page);
      $query = new WP_Query( $args );
      if (!$query->have_posts()) {
        $items=null;
      } else {
        $items = [];
        foreach ($query->posts as $post) {
          $items[] = get_post_data($post);
        }
      }
      $results = [
        "numItems" => (int) $query->found_posts,
        "numPages" => $query->max_num_pages,
        "pageSize" => $args["posts_per_page"] ? $args["posts_per_page"] : intval(get_option( 'posts_per_page' )),
        "currentPage" => (!is_null($args["paged"])) ? $args["paged"] : 1,
        "items" => $items
      ];
      return $results;
    }
    $filters = (isset($parameters["filters"])) ? json_decode($parameters["filters"],true) : [];
    $start = (isset($parameters["start"])) ? $parameters["start"] : 0;
    $limit = (isset($parameters["limit"])) ? $parameters["limit"] : 10;
    $offset = (isset($parameters["offset"])) ? $parameters["offset"] : 0;
    $query = (isset($parameters["query"])) ? $parameters["query"] : null;
    $order = (isset($parameters["order"])) ? $parameters["order"] : "DESC";
    $orderBy = (isset($parameters["orderby"])) ? $parameters["orderby"] : "date";
    $tax_query = [];
    list($page, $pageSize, $headWaste, $tailWaste) = getPageSize($start, $limit);
    if (!empty($filters["scalarFilters"])) {
        foreach ($filters["scalarFilters"] as $scalarFilter) {
            if ($scalarFilter["field"] == "category" || $scalarFilter["field"] == "post_tag") {
                if ($scalarFilter["operator"] == "eq") {
                  $tax_query[] = [
                    "taxonomy" => $scalarFilter["field"],
                    "field" => "slug",
                    "terms" => [$scalarFilter["value"]]
                  ];
                }
            }
        }
    }
    $args = [
      "post_type" => "post",
    	"tax_query" => $tax_query,
      "posts_per_page" => $pageSize,
      "paged" => $page,
      "order" => $order,
      "orderBy" => $orderBy
    ];
    if (isset($query)) $args["s"]=$query;
    $query = new WP_Query( $args );
    if (!$query->have_posts()) {
      $items=null;
    } else {
      $items = [];
      foreach ($query->posts as $post) {
        $items[] = get_post_data($post);
      }
    }
    $results = [
      "numItems" => (int) $query->found_posts,
      "numPages" => $query->max_num_pages,
      "pageSize" => $pageSize,
      "currentPage" => $page,
      "items" => array_slice($items, $headWaste + $offset, count($items)-$headWaste-$offset-$tailWaste)
    ];
    return $results;
}

function getPageSize ($start, $limit) {
  for ($window = $limit; $window <= $start + $limit; $window++) {
    for ($leftShift = 0; $leftShift <= $window - $limit; $leftShift++) {
      if (($start - $leftShift) % $window == 0) {
        $pageSize = $window;
        $page = ($start - $leftShift) / $pageSize;
        $headWaste = $leftShift;
        $tailWaste = (($page + 1) * $pageSize) - ($start + $limit);
        return [$page+1, $pageSize, $headWaste, $tailWaste];
      }
    }
  }
}

function get_item( $data ) {
  $post = get_post($data["id"]);
  return get_post_data($post);
}

function get_model ( $data ) {
    $model = [
        "article" => [
            "name" => "article",
            "description" => "Article",
            "schemaOrgType" => "Article",
            "fields" => [
                "id" => [
                    "type" => "ID",
                    "description" => "Article ID",
                    "required" => true
                ],
                "headline" => [
                    "type" => "String",
                    "required" => true,
                    "description" => "Article Title",
                    "schemaOrgProperty" => "headline"
                ],
                "description" => [
                    "type" => "String",
                    "description" => "Article Description",
                    "schemaOrgProperty" => "description"
                ],
                "image" => [
                    "type" => "ImageURL",
                    "description" => "Article Image",
                    "schemaOrgProperty" => "image"
                ],
                "thumbnail" => [
                    "type" => "ImageURL",
                    "description" => "Article Thumbnail"
                ],
                "body" => [
                    "type" => "HTML",
                    "description" => "Article Body"
                ],
                "authorName" => [
                    "type" => "String",
                    "description" => "Article Author",
                    "schemaOrgProperty" => "author.name"
                ],
                "datePublished" => [
                    "type" => "DateTime",
                    "description" => "Date Published",
                    "schemaOrgProperty" => "datePublished"
                ],
                "dateModified" => [
                    "type" => "DateTime",
                    "description" => "Date Modified",
                    "schemaOrgProperty" => "dateModified"
                ],
                "tags" => [
                    "type" => "tag",
                    "multivalued" => true
                ],
                "categories" => [
                    "type" => "category",
                    "multivalued" => true
                ]
            ],
            "expose" => true,
            "multiEndpoint" => [
                "name" => "articles",
                "args" => [
                    "query" => [
                      "type" => "String"
                    ],
                    "category" => [
                        "type" => "String"
                    ],
                    "tag" => [
                      "type" => "String"
                    ],
                    "route" => [
                      "type" => "String"
                    ]
                ]
            ],
            "singleEndpoint" => [
                "name" => "article",
                "args" => [
                    "id" => [
                        "type" => "ID",
                        "required" => true
                    ]
                ]
            ],
            "connector" => [
                "configs" => [
                    "segment" => "wp-json/ampize/v1/items",
                    "detailSegment" => "wp-json/ampize/v1/item/{id}"
                ]
            ]
        ],
        "tag" => [
            "name" => "tag",
            "fields" => [
                "name" => [
                    "type" => "String"
                ],
                "filter" => [
                    "type" => "String"
                ],
                "value" => [
                    "type" => "String"
                ]
            ]
        ],
        "category" => [
            "name" => "category",
            "fields" => [
                "name" => [
                    "type" => "String"
                ],
                "filter" => [
                    "type" => "String"
                ],
                "value" => [
                    "type" => "String"
                ]
            ]
        ]
    ];
    return $model;
}

function get_navigation( $data ) {
  $locations = get_nav_menu_locations();
  $results=[];
  foreach($locations as $location => $menuid) {
    $menu = wp_get_nav_menu_object($menuid);
    if (!isset($menus[$menuid])) {
      $pages = wp_get_nav_menu_items($menu->term_id);
      break; // find the first menu only
    }
  }
  foreach($pages as $page) {
    $itemid = null;
    $body = null;
    $listFilters = null;
    switch ($page->object) {
      case "category":
        $type = "list";
        $listFilters = [
          "category" => $page->object_id
        ];
        break;
      case "post":
        $type = "item";
        $itemid = $page->object_id;
        break;
      case "custom":
        $type = "link";
        break;
      case "page":
        $type = "html";
        $post = get_post($page->object_id);
        $body =  apply_filters( "the_content", $post->post_content );
        break;
    }
    $results[] = [
      "id" => $page->ID,
      "title" => $page->title,
      "description" => $page->description,
      "keywords" => "",
      "canonicalUrl" => $page->url,
      "urlSegment" => parse_url($page->url)["path"]."?".parse_url($page->url)["query"],
      "isRoot" => ($page->menu_item_parent==0) ? true : false,
      "order" => $page->menu_order,
      "parentId" => $page->menu_item_parent,
      "exludeFromMenu" => False,
      "type" => $type,
      "body" => $body,
      "itemId" => $itemid,
      "listFilters" => $listFilters
    ];
  }
  return ["pages" => $results];
}

function get_post_thumbnail ($post) {
    if (!$img_id = get_post_thumbnail_id ($post->ID)) {
      $attachments = get_children([
        'post_parent' => $post->ID,
        'post_type' => 'attachment',
        'numberposts' => 1,
        'post_mime_type' => 'image'
      ]);
      if (is_array($attachments)) {
        foreach ($attachments as $a) {
          $img_id = $a->ID;
        }
      }
    }
    if ($img_id) {
      $image = wp_get_attachment_image_src($img_id);
      return $image[0];
    } else {
      if ($img = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_content, $matches)) {
        $image = $matches[1][0];
        if (is_int($image)) {
            $image = wp_get_attachment_image_src($image);
            return $image[0];
        } else {
          return $image;
        }
      }
    }
    return false;
}

function get_post_data ($post) {
  if ( empty( $post ) ) {
    return null;
  }
  $datePublished = new DateTime($post->post_date);
  $dateModified = new DateTime($post->post_modified);
  $wp_categories = get_the_category($post->ID);
  $wp_tags = get_the_tags($post->ID);
  if ($wp_categories) {
    $categories = [];
    foreach ($wp_categories as $term) {
      $categories[] = [
        "name" => $term->name,
        "filter" => "category",
        "value" => $term->slug
      ];
    }
  } else {
    $categories = null;
  }
  if ($wp_tags) {
    $tags = [];
    foreach ($wp_tags as $term) {
      $tags[] = [
        "name" => $term->name,
        "filter" => "post_tag",
        "value" => $term->slug
      ];
    }
  } else {
    $tags = null;
  }
  /*
  $comments = get_comments([
    "post_id" => $post->ID,
    "status" => "approve"
  ]);
  $display = wp_list_comments(array(
            "per_page" => 10, //Allow comment pagination
            "reverse_top_level" => false //Show the latest comments at the top of the list
        ), $comments);
  var_dump($display);
  */
  $image = wp_get_attachment_url(get_post_thumbnail_id($post->ID),"thumbnail");

  $thumbnail = get_post_thumbnail($post);

  $description = get_the_excerpt($post);

  $result = [
    "id" => $post->ID,
    "headline" => $post->post_title,
    "description" => $description,
    "image" => $image ? $image : null,
    "thumbnail" => $thumbnail ? $thumbnail : null,
    "body" => $post->post_content,
    "authorName" => get_the_author_meta("display_name",$post->post_author),
    "datePublished" => $datePublished->format(DateTime::ISO8601),
    "dateModified" => $dateModified->format(DateTime::ISO8601),
    "categories" => $categories,
    "tags" => $tags
  ];
  return $result;
}

add_action( "rest_api_init", function () {
  register_rest_route( "ampize/v1", "/site", [
    "methods" => "GET",
    "callback" => "get_site",
  ]);
  register_rest_route( "ampize/v1", "/model", [
    "methods" => "GET",
    "callback" => "get_model",
  ]);
  register_rest_route( "ampize/v1", "/pages", [
    "methods" => "GET",
    "callback" => "get_navigation",
  ]);
  register_rest_route( "ampize/v1", "/items", [
    "methods" => "GET",
    "callback" => "get_items",
  ]);
  register_rest_route( "ampize/v1", "/item/(?P<id>\d+)", [
    "methods" => "GET",
    "callback" => "get_item",
  ]);
});

add_action("admin_menu", "test_plugin_setup_menu");

function test_plugin_setup_menu(){
  add_menu_page( "AMPize Plugin Page", "AMPize Plugin", "manage_options", "ampize-plugin", "ampize_admin" );
}



function ampize_admin(){
  //must check that the user has the required capability
  if (!current_user_can("manage_options"))
  {
    wp_die( __("You do not have sufficient permissions to access this page.") );
  }

  // variables for the field and option names
  $opt_name = "ampize_token";
  $hidden_field_name = "ampize_submit_hidden";
  $data_field_name = "ampize_token";

  // Read in existing option value from database
  $opt_val = get_option( $opt_name );

  // See if the user has posted us some information
  // If they did, this hidden field will be set to "Y"
  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == "Y" ) {
      // Read their posted value
      $opt_val = $_POST[ $data_field_name ];

      // Save the posted value in the database
      update_option( $opt_name, $opt_val );

      // Put a "settings saved" message on the screen

?>
<div class="updated"><p><strong><?php _e("settings saved.", "ampize-plugin" ); ?></strong></p></div>
<?php

  }

  // Now display the settings editing screen

  echo '<div class="wrap">';

  // header

  echo "<h2>" . __( "AMPize.me Plugin Settings", "ampize-plugin" ) . "</h2>";

  // settings form

  ?>

<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

<p><?php _e("AMPize.me Token      ", "ampize-plugin" ); ?>
<input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val; ?>" size="20">
</p><hr />

<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e("Save Changes") ?>" />
</p>

</form>
</div>

<?php

}
