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
    $filters = (isset($parameters["filters"])) ? json_decode($parameters["filters"],true) : [];
    $start = (isset($parameters["start"])) ? $parameters["start"] : 0;
    $limit = (isset($parameters["limit"])) ? $parameters["limit"] : 10;
    list($page, $pageSize, $headWaste, $tailWaste) = getPageSize($start, $limit);
    $offset = (isset($parameters["offset"])) ? $parameters["offset"] : 0;
    $argsMode = "manual";
    $tax_query = [];
    if (!empty($filters["scalarFilters"])) {
        foreach ($filters["scalarFilters"] as $scalarFilter) {
            switch ($scalarFilter["field"]) {
                case "category":
                    $taxonomy = "category";
                    break;
                case "tag":
                    $taxonomy = "post_tag";
                    break;
                case "query":
                    $taxonomy = NULL;
                    $queryFilter = $scalarFilter["value"];
                    break;
                case "url":
                    $taxonomy = NULL;
                    $argsMode = "automatic";
                    $urlToResolve = $scalarFilter["value"];
                    break;
                default:
                    $taxonomy = NULL;
                    break;
            }
            if (!is_null($taxonomy)) {
              $tax_query[] = [
                "taxonomy" => $taxonomy,
                "field" => "slug",
                "terms" => [$scalarFilter["value"]]
              ];
            }
        }
    }

    if ($argsMode=="manual") {
        $order = (isset($parameters["orderByDirection"])) ? $parameters["orderByDirection"] : "DESC";
        $orderBy = (isset($parameters["orderBy"])) ? $parameters["orderBy"] : "date";
        $args = [
          "post_type" => "post",
        	"tax_query" => $tax_query,
          "order" => $order,
          "orderby" => $orderBy
        ];
        $queryFilter = (isset($parameters["keywords"])) ? $parameters["keywords"] : NULL;
        if (isset($queryFilter)) $args["s"]=$queryFilter;
        if ($orderBy=="views") {
          $args["meta_key"] = "ampize_views";
          $args["orderby"] = "meta_value_num";
        }
    }
    if ($argsMode == "automatic") {
        $resolver = new UrlToQuery();
        $args = $resolver->resolve($urlToResolve);
    }
    $args["posts_per_page"] = $pageSize;
    $args["paged"] = $page;

    $query = new WP_Query( $args );
    if (!$query->have_posts()) {
      $items=null;
    } else {
      $items = [];
      foreach ($query->posts as $post) {
        $item = get_post_data($post);
        $item["views"] =  intval(get_post_meta($post->ID, "ampize_views", true));
        $items[] = $item;
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
  $result = get_post_data($post);
  $result["relatedPosts"] = get_related_posts($post);
  return $result;
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
                "url" => [
                    "type" => "String",
                    "description" => "Post Url"
                ],
                "urlSegment" => [
                    "type" => "String",
                    "description" => "Url path"
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
                "excerpt" => [
                    "type" => "String",
                    "description" => "Article Excerpt"
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
                "views" => [
                    "type" => "String",
                    "description" => "Article views"
                ],
                "tags" => [
                    "type" => "tag",
                    "multivalued" => true
                ],
                "categories" => [
                    "type" => "category",
                    "multivalued" => true
                ],
                "relatedPosts" => [
                    "type" => "relatedPost",
                    "multivalued" => true
                ]
            ],
            "expose" => true,
            "multiEndpoint" => [
                "name" => "articles",
                "args" => [
                    "keywords" => [
                      "type" => "String"
                    ],
                    "category" => [
                        "type" => "String"
                    ],
                    "tag" => [
                      "type" => "String"
                    ],
                    "url" => [
                      "type" => "String"
                    ]
                ],
                "sortKeys" => [
                    [
                        "value" => "date",
                        "label" => "Publication date"
                    ],
                    [
                        "value" => "views",
                        "label" => "Most viewed"
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
        ],
        "relatedPost" => [
          "name" => "relatedPost",
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
            "excerpt" => [
                "type" => "String",
                "description" => "Article Excerpt"
            ],
            "image" => [
                "type" => "ImageURL",
                "description" => "Article Image",
                "schemaOrgProperty" => "image"
            ],
            "thumbnail" => [
                "type" => "ImageURL",
                "description" => "Article Thumbnail"
            ]
          ]
        ],
        "page" => [
            "name" => "page",
            "description" => "Page",
            "fields" => [
                "id" => [
                    "type" => "ID",
                    "description" => "Page ID",
                    "required" => true
                ],
                "name" => [
                    "type" => "String",
                    "description" => "Page Title"
                ],
                "type" => [
                    "type" => "String",
                    "description" => "Page Type: list, item or link"
                ],
                "urlSegment" => [
                    "type" => "String",
                    "description" => "Url segment"
                ],
                "description" => [
                    "type" => "String",
                    "description" => "Page Description"
                ],
                "keywords" => [
                    "type" => "String",
                    "description" => "Page key words"
                ],
                "url" => [
                    "type" => "String",
                    "description" => "Page Url"
                ],
                "isRoot" => [
                    "type" => "Boolean",
                    "description" => "Is this page the home page?"
                ],
                "order" => [
                    "type" => "String",
                    "description" => "Page order in menu or sub-menu"
                ],
                "parentId" => [
                    "type" => "ID",
                    "description" => "Parent page ID"
                ],
                "excludeFromMenu" => [
                    "type" => "Boolean",
                    "description" => "Is this page excluded from menu?"
                ],
                "children" => [
                  "type" => "page",
                  "multivalued" =>true,
                  "args" =>[
                    "name" => [
                      "type" => "String"
                    ],
                    "siteId" => [
                      "type" => "ID"
                    ],
                    "segment" => [
                      "type" => "String"
                    ]
                  ],
                  "relation" => [
                    "parentId" => "id"
                  ]
                ]
            ]
        ]
    ];
    return $model;
}

function get_menu($request) {
    $parameters = $request->get_query_params();
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
        if (!isset($parameters["parentId"]) or (isset($parameters["parentId"]) && ($page->menu_item_parent == $parameters["parentId"]))) {
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
                $body = apply_filters("the_content", $post->post_content);
                break;
            }
            $segment = parse_url($page->url)["path"];
            if (isset(parse_url($page->url)["query"])) {
              $segment.="?".parse_url($page->url)["query"];
            }
            $results[] = [
              "id" => $page->ID,
              "name" => $page->title,
              "description" => $page->description,
              "keywords" => "",
              "url" => $page->url,
              "urlSegment" => $segment,
              "isRoot" => ($page->menu_item_parent==0) ? true : false,
              "order" => $page->menu_order,
              "parentId" => intval($page->menu_item_parent),
              "excludeFromMenu" => False,
              "type" => $type,
              "body" => $body,
              "itemId" => $itemid,
              //"listFilters" => $listFilters
            ];
        }
    }
    return ["items" => $results];
}

function get_related_posts($post) {
  $tags = wp_get_post_tags($post->ID);
  if ($tags) {
    $tag_ids = array();
    foreach($tags as $individual_tag) $tag_ids[] = $individual_tag->term_id;
    $limit = get_option("ampize_relatedposts_limit") ? get_option("ampize_relatedposts_limit") : 5;
    $args=[
      "tag__in" => $tag_ids,
      "post__not_in" => [$post->ID],
      "posts_per_page"=>$limit,
      "caller_get_posts"=>1
    ];
    $query = new wp_query($args);
    if (!$query->have_posts()) {
      $items=null;
    } else {
      $items = [];
      foreach ($query->posts as $post) {
        $item = get_post_data($post);
        $item["views"] =  intval(get_post_meta($post->ID, "ampize_views", true));
        $items[] = $item;
      }
    }
  }
  return $items;
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

  $description =  !empty($post->post_excerpt) ? get_the_excerpt($post) : null;

  $excerpt = excerpt($post);

  $url = get_permalink($post->ID);

  $segment = parse_url($url)["path"];
  if (isset(parse_url($url)["query"])) {
    $segment.="?".parse_url($url)["query"];
  }

  $result = [
    "id" => $post->ID,
    "headline" => $post->post_title,
    "description" => $description,
    "excerpt" => $excerpt,
    "image" => $image ? $image : null,
    "thumbnail" => $thumbnail ? $thumbnail : null,
    "body" => apply_filters('the_content', $post->post_content),
    "authorName" => get_the_author_meta("display_name",$post->post_author),
    "datePublished" => $datePublished->format(DateTime::ISO8601),
    "dateModified" => $dateModified->format(DateTime::ISO8601),
    "categories" => $categories,
    "tags" => $tags,
    "url" => $url,
    "urlSegment" => $segment
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
    "callback" => "get_menu",
  ]);
  register_rest_route( "ampize/v1", "/items", [
    "methods" => "GET",
    "callback" => "get_items",
  ]);
  register_rest_route( "ampize/v1", "/item/(?P<id>\d+)", [
    "methods" => "GET",
    "callback" => "get_item",
  ]);
  register_rest_route( "ampize/v1", "/views/(?P<id>\d+)", [
    "methods" => "GET",
    "callback" => "post_view_counter_function",
  ]);
});

function excerpt($post) {
  $limit = get_option("ampize_excerpt_length");
  $full_excerpt = !empty($post->post_excerpt) ? get_the_excerpt($post) : null;
  if ($limit && !is_null($full_excerpt)) {
    $excerpt = explode(' ', $full_excerpt, $limit);
    if (($excerpt != '') && (count($excerpt)>=$limit)) {
      array_pop($excerpt);
      $excerpt = implode(" ",$excerpt).' [...]';
    } else {
      $excerpt = implode(" ",$excerpt);
    }
    $excerpt = preg_replace('`[[^]]*]`','',$excerpt);
  } else {
    $excerpt = $full_excerpt;
  }
  return $excerpt;
}

function post_view_counter_function($request) {
  $post_id = $request['id'];
  if ( FALSE === get_post_status( $post_id ) ) {
    return new WP_Error( 'error_no_post', 'Not a post id', array( 'status' => 404 ) );
  } else {
    $current_views = get_post_meta( $post_id, 'ampize_views', true );
    $views = ($current_views ? $current_views : 0) + 1;
    update_post_meta( $post_id, 'ampize_views', intval($views) );
    return $views;
  }
}

// create custom plugin settings menu
add_action('admin_menu', 'ampize_plugin_create_menu');

function ampize_plugin_create_menu() {

	//create new top-level menu
	add_menu_page('AMPize.me PSettings', 'AMPize.me', 'administrator', __FILE__, 'ampize_plugin_settings_page' , plugins_url('/images/icon.png', __FILE__) );

	//call register settings function
	add_action( 'admin_init', 'register_ampize_plugin_settings' );
}


function register_ampize_plugin_settings() {
	//register our settings
	register_setting( 'ampize-plugin-settings-group', 'ampize_excerpt_length' );
	register_setting( 'ampize-plugin-settings-group', 'ampize_relatedposts_limit' );
}

function ampize_plugin_settings_page() {
?>
<div class="wrap">
<h1>AMPize.me Settings</h1>

<form method="post" action="options.php">
    <?php settings_fields( 'ampize-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'ampize-plugin-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Excerpt length</th>
        <td><input type="text" name="ampize_excerpt_length" value="<?php echo esc_attr( get_option('ampize_excerpt_length') ); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row">Number of related posts</th>
        <td><input type="text" name="ampize_relatedposts_limit" value="<?php echo esc_attr( get_option('ampize_relatedposts_limit') ); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>

</form>
</div>
<?php } ?>
