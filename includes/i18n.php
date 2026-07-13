<?php
/**
 * includes/i18n.php — lightweight interface translations.
 *
 * Supported languages: English (en), Khmer (km), Chinese/中文 (zh).
 * Only the INTERFACE is translated (buttons, labels, headings, messages);
 * menu item names/descriptions stay exactly as the admin entered them.
 *
 * Usage:  echo t('add_to_cart');   // returns the string in the current language
 *
 * Language is chosen via ?lang=km|zh|en (remembered in the session + a cookie),
 * so a visitor's choice sticks across pages and visits.
 *
 * This file is required from includes/functions.php, which starts the session,
 * so t() and current_lang() are available on every page before any output.
 */

/** code => short label shown in the switcher. */
const TB_LANGS = ['en' => 'EN', 'km' => 'ខ្មែរ', 'zh' => '中文'];

/** Resolve the active language from ?lang / session / cookie, defaulting to en. */
function tb_detect_lang(): string
{
    $supported = array_keys(TB_LANGS);

    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $_SESSION['lang'] = $_GET['lang'];
        // Remember for a year (best-effort; ignored if headers already sent).
        @setcookie('tb_lang', $_GET['lang'], time() + 31536000, '/');
        return $_GET['lang'];
    }
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $supported, true)) {
        return $_SESSION['lang'];
    }
    if (!empty($_COOKIE['tb_lang']) && in_array($_COOKIE['tb_lang'], $supported, true)) {
        return $_SESSION['lang'] = $_COOKIE['tb_lang'];
    }
    return 'en';
}

$GLOBALS['tb_lang'] = tb_detect_lang();

/** The active language code (en|km|zh). */
function current_lang(): string
{
    return $GLOBALS['tb_lang'] ?? 'en';
}

/**
 * Translate a key into the current language. Falls back to English, then to
 * the key itself. Extra args are applied with sprintf (e.g. t('n_found', 3)).
 */
function t(string $key, ...$args): string
{
    $dict = tb_translations();
    $lang = current_lang();
    $str  = $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
    if ($args) {
        $str = vsprintf($str, $args);
    }
    return $str;
}

/** Build a URL for the current page with a different ?lang= value. */
function lang_switch_url(string $code): string
{
    $params = $_GET;
    $params['lang'] = $code;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/menu/index.php', '?');
    return $path . '?' . http_build_query($params);
}

/** The whole translation table (lazy-built once per request). */
function tb_translations(): array
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }

    $t = [];

    // ---- English (source of truth; also the fallback) ----
    $t['en'] = [
        // Header / nav
        'nav_menu'        => 'Menu',
        'nav_signin'      => 'Sign in',
        'nav_cart'        => 'Cart',
        'nav_favourites'  => 'Favourites',
        'language'        => 'Language',

        // Hero / menu landing
        'hero_eyebrow'    => 'Order online',
        'hero_title'      => 'Delicious food, delivered fresh to your table.',
        'hero_text'       => 'Browse our chef-crafted menu, add your favourites to the cart, and check out in seconds — dine-in or takeaway.',
        'hero_cta'        => 'Explore the menu ↓',
        'our_menu'        => 'Our Menu',
        'menu_subtitle'   => 'Freshly made, just for you.',
        'search_ph'       => 'Search dishes…',
        'search'          => 'Search',
        'clear'           => 'Clear',
        'all'             => 'All',
        'add'             => 'Add',
        'n_found'         => '%d item(s) found',
        'for_q'           => 'for',
        'special_offers'  => 'Special Offers',
        'n_deals'         => '%d deal(s)',
        'no_menu'         => 'No menu available yet',
        'check_back'      => 'Please check back soon.',
        'no_dishes'       => 'No dishes found',
        'try_diff'        => 'Try a different search or category.',
        'show_all'        => 'Show all',

        // Popup
        'promo_deal'      => '🔥 Limited-time deal',
        'promo_fav'       => '⭐ Customer favourite',
        'add_to_cart'     => 'Add to cart',
        'view_details'    => 'View details',

        // Cart
        'your_cart'       => 'Your Cart',
        'cart_empty'      => 'Your cart is empty',
        'cart_empty_sub'  => 'Add some delicious items from our menu.',
        'browse_menu'     => 'Browse Menu',
        'th_item'         => 'Item',
        'th_price'        => 'Price',
        'th_qty'          => 'Quantity',
        'th_subtotal'     => 'Subtotal',
        'update'          => 'Update',
        'remove'          => 'Remove',
        'total'           => 'Total',
        'continue_shop'   => '← Continue Shopping',
        'clear_cart'      => 'Clear Cart',
        'to_checkout'     => 'Proceed to Checkout →',

        // Checkout
        'checkout'        => 'Checkout',
        'your_details'    => 'Your Details',
        'order_summary'   => 'Order Summary',
        'name'            => 'Name',
        'phone'           => 'Phone',
        'order_type'      => 'Order Type',
        'dine_in'         => 'Dine In',
        'takeaway'        => 'Takeaway',
        'table_number'    => 'Table Number',
        'notes_opt'       => 'Notes (optional)',
        'place_order'     => 'Place Order',
        'tax'             => 'Tax',
        'service'         => 'Service',
        'ordering_as'     => 'Ordering as',
        'err_name'        => 'Please enter your name.',
        'err_type'        => 'Please choose a valid order type.',
        'err_table'       => 'Please enter your table number for dine-in orders.',
        'err_save'        => 'Sorry, we could not place your order. Please try again.',

        // Order confirmation
        'thank_you'       => 'Thank you',
        'order_received'  => 'has been received.',
        'order_word'      => 'Your order',
        'status'          => 'Status',
        'table'           => 'Table',
        'notes'           => 'Notes',
        'view_invoice'    => '🧾 View / Print Invoice',
        'download_pdf'    => '⬇ Download PDF',
        'order_more'      => 'Order More',
        'order_confirmed' => 'Order Confirmed',

        // Food detail
        'add_to_cart_p'   => '＋ Add to cart',
        'unavailable'     => 'Currently unavailable',
        'save_fav'        => '🤍 Favourite',
        'saved_fav'       => '❤️ Saved',
        'your_rating'     => 'Your rating:',
        'rate_dish'       => 'Rate this dish:',
        'no_ratings'      => 'No ratings yet',
        'ratings_n'       => 'rating(s)',
        'comments'        => 'Comments',
        'post_comment'    => 'Post comment',
        'comment_ph'      => 'Share what you thought about this dish…',
        'signin_to_rate'  => 'sign in',
        'signin_comment'  => 'to leave a comment.',
        'no_comments'     => 'No comments yet — be the first!',
        'no_desc'         => 'No description available.',

        // Favourites
        'my_favourites'   => 'My Favourites',
        'fav_subtitle'    => 'The dishes you saved.',
        'no_favs'         => 'No favourites yet',
        'no_favs_sub'     => 'Open any dish and tap Favourite to save it here.',

        // Admin
        'a_dashboard'     => 'Dashboard',
        'a_orders'        => 'Orders',
        'a_kitchen'       => '👨‍🍳 Kitchen',
        'a_categories'    => 'Categories',
        'a_items'         => 'Menu Items',
        'a_settings'      => 'Settings',
        'a_view_site'     => 'View Site ↗',
        'a_logout'        => 'Logout',
        'a_admin'         => 'Admin',
        'a_chef'          => 'Chef',
        'a_orders_today'  => 'Orders Today',
        'a_active_orders' => 'Active Orders',
        'a_menu_items'    => 'Menu Items',
        'a_revenue_today' => 'Revenue Today',
        'a_recent_orders' => 'Recent Orders',
        'a_view_all'      => 'View all',
        'a_top_seller'    => 'Top Seller',
        'a_sold'          => 'sold',
        'a_earned'        => 'earned',
        'a_orders_food'   => '📊 Orders by Food',
        'a_units_sold'    => 'Units sold (all time)',
        'a_no_orders'     => 'No orders yet.',
        'a_customer'      => 'Customer',
        'a_type'          => 'Type',
        'a_time'          => 'Time',
    ];

    // ---- Khmer (ភាសាខ្មែរ) ----
    $t['km'] = [
        'nav_menu'        => 'ម៉ឺនុយ',
        'nav_signin'      => 'ចូលគណនី',
        'nav_cart'        => 'កន្ត្រក',
        'nav_favourites'  => 'ចំណូលចិត្ត',
        'language'        => 'ភាសា',

        'hero_eyebrow'    => 'បញ្ជាទិញតាមអនឡាញ',
        'hero_title'      => 'ម្ហូបឆ្ងាញ់ ដឹកជញ្ជូនស្រស់ៗដល់តុរបស់អ្នក។',
        'hero_text'       => 'រុករកម៉ឺនុយរបស់យើង បន្ថែមម្ហូបចូលកន្ត្រក ហើយបង់ប្រាក់ក្នុងរយៈពេលពីរបីវិនាទី — ញ៉ាំនៅភោជនីយដ្ឋាន ឬយកទៅផ្ទះ។',
        'hero_cta'        => 'មើលម៉ឺនុយ ↓',
        'our_menu'        => 'ម៉ឺនុយរបស់យើង',
        'menu_subtitle'   => 'ធ្វើឡើងស្រស់ៗ សម្រាប់អ្នក។',
        'search_ph'       => 'ស្វែងរកម្ហូប…',
        'search'          => 'ស្វែងរក',
        'clear'           => 'សម្អាត',
        'all'             => 'ទាំងអស់',
        'add'             => 'បន្ថែម',
        'n_found'         => 'រកឃើញ %d មុខ',
        'for_q'           => 'សម្រាប់',
        'special_offers'  => 'ការផ្តល់ជូនពិសេស',
        'n_deals'         => '%d ការផ្តល់ជូន',
        'no_menu'         => 'មិនទាន់មានម៉ឺនុយនៅឡើយទេ',
        'check_back'      => 'សូមត្រឡប់មកវិញនៅពេលក្រោយ។',
        'no_dishes'       => 'រកមិនឃើញម្ហូប',
        'try_diff'        => 'សូមសាកល្បងស្វែងរក ឬប្រភេទផ្សេង។',
        'show_all'        => 'បង្ហាញទាំងអស់',

        'promo_deal'      => '🔥 ការផ្តល់ជូនមានកំណត់',
        'promo_fav'       => '⭐ ម្ហូបពេញនិយម',
        'add_to_cart'     => 'បញ្ចូលកន្ត្រក',
        'view_details'    => 'មើលព័ត៌មានលម្អិត',

        'your_cart'       => 'កន្ត្រករបស់អ្នក',
        'cart_empty'      => 'កន្ត្រករបស់អ្នកទទេ',
        'cart_empty_sub'  => 'បន្ថែមម្ហូបឆ្ងាញ់ៗពីម៉ឺនុយរបស់យើង។',
        'browse_menu'     => 'មើលម៉ឺនុយ',
        'th_item'         => 'ម្ហូប',
        'th_price'        => 'តម្លៃ',
        'th_qty'          => 'ចំនួន',
        'th_subtotal'     => 'សរុបរង',
        'update'          => 'ធ្វើបច្ចុប្បន្នភាព',
        'remove'          => 'លុបចេញ',
        'total'           => 'សរុប',
        'continue_shop'   => '← បន្តទិញ',
        'clear_cart'      => 'សម្អាតកន្ត្រក',
        'to_checkout'     => 'បន្តទៅបង់ប្រាក់ →',

        'checkout'        => 'បង់ប្រាក់',
        'your_details'    => 'ព័ត៌មានរបស់អ្នក',
        'order_summary'   => 'សេចក្តីសង្ខេបការបញ្ជាទិញ',
        'name'            => 'ឈ្មោះ',
        'phone'           => 'ទូរស័ព្ទ',
        'order_type'      => 'ប្រភេទការបញ្ជាទិញ',
        'dine_in'         => 'ញ៉ាំនៅទីនេះ',
        'takeaway'        => 'យកទៅផ្ទះ',
        'table_number'    => 'លេខតុ',
        'notes_opt'       => 'កំណត់ចំណាំ (ស្រេចចិត្ត)',
        'place_order'     => 'បញ្ជាទិញ',
        'tax'             => 'ពន្ធ',
        'service'         => 'សេវាកម្ម',
        'ordering_as'     => 'បញ្ជាទិញក្នុងនាម',
        'err_name'        => 'សូមបញ្ចូលឈ្មោះរបស់អ្នក។',
        'err_type'        => 'សូមជ្រើសរើសប្រភេទការបញ្ជាទិញត្រឹមត្រូវ។',
        'err_table'       => 'សូមបញ្ចូលលេខតុសម្រាប់ការញ៉ាំនៅទីនេះ។',
        'err_save'        => 'សូមអភ័យទោស យើងមិនអាចដាក់ការបញ្ជាទិញបានទេ។ សូមព្យាយាមម្តងទៀត។',

        'thank_you'       => 'អរគុណ',
        'order_received'  => 'ត្រូវបានទទួល។',
        'order_word'      => 'ការបញ្ជាទិញរបស់អ្នក',
        'status'          => 'ស្ថានភាព',
        'table'           => 'តុ',
        'notes'           => 'កំណត់ចំណាំ',
        'view_invoice'    => '🧾 មើល / បោះពុម្ពវិក្កយបត្រ',
        'download_pdf'    => '⬇ ទាញយក PDF',
        'order_more'      => 'បញ្ជាទិញបន្ថែម',
        'order_confirmed' => 'ការបញ្ជាទិញបានបញ្ជាក់',

        'add_to_cart_p'   => '＋ បញ្ចូលកន្ត្រក',
        'unavailable'     => 'បច្ចុប្បន្នមិនមាន',
        'save_fav'        => '🤍 ចូលចិត្ត',
        'saved_fav'       => '❤️ បានរក្សាទុក',
        'your_rating'     => 'ការវាយតម្លៃរបស់អ្នក៖',
        'rate_dish'       => 'វាយតម្លៃម្ហូបនេះ៖',
        'no_ratings'      => 'មិនទាន់មានការវាយតម្លៃ',
        'ratings_n'       => 'ការវាយតម្លៃ',
        'comments'        => 'មតិយោបល់',
        'post_comment'    => 'បង្ហោះមតិ',
        'comment_ph'      => 'ចែករំលែកអ្វីដែលអ្នកគិតអំពីម្ហូបនេះ…',
        'signin_to_rate'  => 'ចូលគណនី',
        'signin_comment'  => 'ដើម្បីទុកមតិ។',
        'no_comments'     => 'មិនទាន់មានមតិ — សូមធ្វើជាមនុស្សដំបូង!',
        'no_desc'         => 'មិនមានការពិពណ៌នា។',

        'my_favourites'   => 'ចំណូលចិត្តរបស់ខ្ញុំ',
        'fav_subtitle'    => 'ម្ហូបដែលអ្នកបានរក្សាទុក។',
        'no_favs'         => 'មិនទាន់មានចំណូលចិត្ត',
        'no_favs_sub'     => 'បើកម្ហូបណាមួយ ហើយចុច ចូលចិត្ត ដើម្បីរក្សាទុកនៅទីនេះ។',

        'a_dashboard'     => 'ផ្ទាំងគ្រប់គ្រង',
        'a_orders'        => 'ការបញ្ជាទិញ',
        'a_kitchen'       => '👨‍🍳 ផ្ទះបាយ',
        'a_categories'    => 'ប្រភេទ',
        'a_items'         => 'ម្ហូបក្នុងម៉ឺនុយ',
        'a_settings'      => 'ការកំណត់',
        'a_view_site'     => 'មើលគេហទំព័រ ↗',
        'a_logout'        => 'ចាកចេញ',
        'a_admin'         => 'អ្នកគ្រប់គ្រង',
        'a_chef'          => 'ចុងភៅ',
        'a_orders_today'  => 'ការបញ្ជាទិញថ្ងៃនេះ',
        'a_active_orders' => 'ការបញ្ជាទិញកំពុងដំណើរការ',
        'a_menu_items'    => 'ម្ហូបក្នុងម៉ឺនុយ',
        'a_revenue_today' => 'ចំណូលថ្ងៃនេះ',
        'a_recent_orders' => 'ការបញ្ជាទិញថ្មីៗ',
        'a_view_all'      => 'មើលទាំងអស់',
        'a_top_seller'    => 'ម្ហូបលក់ដាច់ជាងគេ',
        'a_sold'          => 'បានលក់',
        'a_earned'        => 'ចំណូល',
        'a_orders_food'   => '📊 ការបញ្ជាទិញតាមម្ហូប',
        'a_units_sold'    => 'ចំនួនបានលក់ (ទាំងអស់)',
        'a_no_orders'     => 'មិនទាន់មានការបញ្ជាទិញ។',
        'a_customer'      => 'អតិថិជន',
        'a_type'          => 'ប្រភេទ',
        'a_time'          => 'ពេលវេលា',
    ];

    // ---- Chinese / 中文 (Simplified) ----
    $t['zh'] = [
        'nav_menu'        => '菜单',
        'nav_signin'      => '登录',
        'nav_cart'        => '购物车',
        'nav_favourites'  => '收藏',
        'language'        => '语言',

        'hero_eyebrow'    => '在线点餐',
        'hero_title'      => '美味佳肴，新鲜送到您的餐桌。',
        'hero_text'       => '浏览我们主厨精心打造的菜单，将喜爱的菜品加入购物车，几秒钟即可结账——堂食或外带。',
        'hero_cta'        => '浏览菜单 ↓',
        'our_menu'        => '我们的菜单',
        'menu_subtitle'   => '新鲜制作，只为您。',
        'search_ph'       => '搜索菜品…',
        'search'          => '搜索',
        'clear'           => '清除',
        'all'             => '全部',
        'add'             => '添加',
        'n_found'         => '找到 %d 道菜',
        'for_q'           => '搜索',
        'special_offers'  => '特惠',
        'n_deals'         => '%d 项优惠',
        'no_menu'         => '暂无菜单',
        'check_back'      => '请稍后再来查看。',
        'no_dishes'       => '未找到菜品',
        'try_diff'        => '请尝试其他搜索或分类。',
        'show_all'        => '显示全部',

        'promo_deal'      => '🔥 限时优惠',
        'promo_fav'       => '⭐ 顾客最爱',
        'add_to_cart'     => '加入购物车',
        'view_details'    => '查看详情',

        'your_cart'       => '您的购物车',
        'cart_empty'      => '您的购物车是空的',
        'cart_empty_sub'  => '从菜单中添加一些美味吧。',
        'browse_menu'     => '浏览菜单',
        'th_item'         => '菜品',
        'th_price'        => '价格',
        'th_qty'          => '数量',
        'th_subtotal'     => '小计',
        'update'          => '更新',
        'remove'          => '移除',
        'total'           => '合计',
        'continue_shop'   => '← 继续购物',
        'clear_cart'      => '清空购物车',
        'to_checkout'     => '前往结账 →',

        'checkout'        => '结账',
        'your_details'    => '您的信息',
        'order_summary'   => '订单摘要',
        'name'            => '姓名',
        'phone'           => '电话',
        'order_type'      => '订单类型',
        'dine_in'         => '堂食',
        'takeaway'        => '外带',
        'table_number'    => '桌号',
        'notes_opt'       => '备注（可选）',
        'place_order'     => '提交订单',
        'tax'             => '税费',
        'service'         => '服务费',
        'ordering_as'     => '下单人',
        'err_name'        => '请输入您的姓名。',
        'err_type'        => '请选择有效的订单类型。',
        'err_table'       => '堂食订单请输入桌号。',
        'err_save'        => '抱歉，无法提交您的订单。请重试。',

        'thank_you'       => '谢谢',
        'order_received'  => '已收到。',
        'order_word'      => '您的订单',
        'status'          => '状态',
        'table'           => '餐桌',
        'notes'           => '备注',
        'view_invoice'    => '🧾 查看 / 打印发票',
        'download_pdf'    => '⬇ 下载 PDF',
        'order_more'      => '继续点餐',
        'order_confirmed' => '订单已确认',

        'add_to_cart_p'   => '＋ 加入购物车',
        'unavailable'     => '暂时缺货',
        'save_fav'        => '🤍 收藏',
        'saved_fav'       => '❤️ 已收藏',
        'your_rating'     => '您的评分：',
        'rate_dish'       => '为这道菜评分：',
        'no_ratings'      => '暂无评分',
        'ratings_n'       => '条评分',
        'comments'        => '评论',
        'post_comment'    => '发表评论',
        'comment_ph'      => '分享您对这道菜的看法…',
        'signin_to_rate'  => '登录',
        'signin_comment'  => '后即可评论。',
        'no_comments'     => '还没有评论——来抢沙发吧！',
        'no_desc'         => '暂无描述。',

        'my_favourites'   => '我的收藏',
        'fav_subtitle'    => '您收藏的菜品。',
        'no_favs'         => '还没有收藏',
        'no_favs_sub'     => '打开任意菜品并点击收藏即可保存到这里。',

        'a_dashboard'     => '仪表板',
        'a_orders'        => '订单',
        'a_kitchen'       => '👨‍🍳 厨房',
        'a_categories'    => '分类',
        'a_items'         => '菜品',
        'a_settings'      => '设置',
        'a_view_site'     => '查看网站 ↗',
        'a_logout'        => '退出',
        'a_admin'         => '管理员',
        'a_chef'          => '厨师',
        'a_orders_today'  => '今日订单',
        'a_active_orders' => '进行中订单',
        'a_menu_items'    => '菜品数量',
        'a_revenue_today' => '今日营收',
        'a_recent_orders' => '最近订单',
        'a_view_all'      => '查看全部',
        'a_top_seller'    => '销量冠军',
        'a_sold'          => '已售',
        'a_earned'        => '营收',
        'a_orders_food'   => '📊 各菜品销量',
        'a_units_sold'    => '销量（全部时间）',
        'a_no_orders'     => '暂无订单。',
        'a_customer'      => '顾客',
        'a_type'          => '类型',
        'a_time'          => '时间',
    ];

    return $t;
}
