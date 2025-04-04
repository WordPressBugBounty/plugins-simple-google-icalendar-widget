<?php
/*
 * SimpleicalHelper.php
 *
 * @package Simple Google iCalendar Widget
 * @subpackage Block
 * @author Bram Waasdorp <bram@waasdorpsoekhan.nl>
 * @copyright Copyright (c) 2022 - 2025, Bram Waasdorp
 * @link https://github.com/bramwaas/wordpress-plugin-wsa-simple-google-calendar-widget
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Gutenberg Block functions since v2.1.2 also used for widget.
 * Version: 2.7.0
 * 2.2.0 20240106 changed text domain to simple-google-icalendar-widget
 * 2.2.1 20240123 don't display description line when excerpt-length = 0
 * 2.3.0 remove definition of attributes, leave it to block.json
 * improvement of working with client timezone: add client timezone as an extra parameter to wp_date because date_default_timezone_set has no effect
 * block default version 3 version 2; add <span class="dsc"> to description output to make it easier to refer to in css
 * title with more wptypes, no display of empty title, title output secured with wp_kses (to display empty title line use <>.
 * 2.3.1 spelling error in render block block/ssr 
 * 2.4.0 str_replace('Etc/GMT ','Etc/GMT+' for some UTC-... timezonesettings.
 * 2.4.1 resolved with wptype 'rest_ph_w' warning on wrapper_attributes when wptype 'rest_ph' and started from widget 
 * 2.4.3 replace render_callback in server side register_block_type by render in block.json (v3 plus ( is_wp_version_compatible( '6.3' ) )) 
   add  "data-sib-utzui":props.attributes.rest_utzui to rest placeholder tag; use tag_title when not placeholder for widget
 * 2.4.4 improve compare equallity in update_rest_attrs by removing attributes that are added during save process or depend on saving environment.
 * 2.5.0 Add filter and display support for categories.
 * 2.6.0 improve security by following Plugin Check recommendations; Moved functions common with Joomla to top. 
   rename SimpleicalBlock to SimpleicalHelper and register widget in this class. 
   Replace echo by $secho in &$secho param a.o. in display_block, to simplify escaping output by replacing multiple echoes by one. 
   known error: in wp 5.9.5 with elementor 3.14.1 aria-expanded and aria-controls are stripped bij wp_kses before wp 6.3.0 (see wp_kses.php) 
    issue is solved tested with wp 6.7.1 with elementor 3.26.5 . 
 * 2.6.1  Started simplifying (bootstrap) collapse by toggles for adding javascript and trigger collapse by title.
   Remove toggle to allow safe html in summary and description, save html is always allowed now.
   Sameday as logical and calculated with localtime instead of gmdate. Add titlenode to REST output. Removed ev_class from li head.
 * 2.7.0 Added cast $class to string in sanitize_html_clss, defaults for new collapse fields. Add support for details/summary tag combination.         
 */
namespace WaasdorpSoekhan\WP\Plugin\SimpleGoogleIcalendarWidget;

class SimpleicalHelper
{
    const SIB_ATTR = 'simple_ical_block_attrs';

    /**
     * tags allowed for summary
     *
     * @var array
     */
    static $allowed_tags_sum = [
        'a',
        'b',
        'div',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'i',
        'span',
        'strong',
        'summary',
        'u'
    ];

    /**
     * default value for block_attributes (or instance)
     *
     * @var array
     */
    static $default_block_attributes = [
        'wptype' => 'block',
        'sibid' => '',
        'postid' => '0',
        'calendar_id' => '',
        'event_count' => 10,
        'event_period' => 92,
        'cache_time' => 60,
        'categories_filter_op' => '',
        'categories_filter' => '',
        'categories_display' => '',
        'add_sum_catflt' => false,
        'layout' => 3,
        'dateformat_lg' => 'l jS \of F',
        'dateformat_lgend' => '',
        'tag_title' => 'h3',
        'tag_sum' => 'a',
        'dateformat_tsum' => 'G:i ',
        'dateformat_tsend' => '',
        'dateformat_tstart' => 'G:i',
        'dateformat_tend' => ' - G:i ',
        'excerptlength' => '',
        'suffix_lg_class' => '',
        'suffix_lgi_class' => ' py-0',
        'suffix_lgia_class' => '',
        'allowhtml' => true,
        'after_events' => '',
        'no_events' => '',
        'clear_cache_now' => false,
        'period_limits' => '1',
        'rest_utzui' => '',
        'className' => '',
        'anchorId' => '',
        'title_collapse_toggle' => '',
        'add_collapse_code' => false,
        'before_widget' => '<div id="%1$s" %2$s>',
        'after_widget'  => '</div>'
    ];

    /**
     * block_attributes excluded from test if changed, because they (most of them) are changed during the save process.
     *
     * @var array
     */
    static  $exclude_test_attrs = [
        'saved' => null,
        '__internalWidgetId' => null,
        '_locale' => null,
        'tzid_ui' => null,
    ];
    /**
     * Front-end display of module, block or widget.
     *
     * @see
     *
     * @param array $attributes
     * @param string &$secho (reference to $secho), output to echo in calling function, to simplify escaping output by replacing multiple echoes by one 
     *            Saved attribute/option values from database.
     */
    static function display_block($attributes, &$secho)
    {
        $sn = 0;
        try {
            $attributes['tz_ui'] = new \DateTimeZone($attributes['tzid_ui']);
        } catch (\Exception $exc) {}
        if (empty($attributes['tz_ui']))
            try {
                $attributes['tzid_ui'] = str_replace('Etc/GMT ','Etc/GMT+',$attributes['tzid_ui']);
                $attributes['tz_ui'] = new \DateTimeZone($attributes['tzid_ui']);
        } catch (\Exception $exc) {}
        if (empty($attributes['tz_ui']))
            try {
                $attributes['tzid_ui'] = wp_timezone_string();
                $attributes['tz_ui'] = new \DateTimeZone($attributes['tzid_ui']);
        } catch (\Exception $exc) {}
        if (empty($attributes['tz_ui'])) {
            $attributes['tzid_ui'] = 'UTC';
            $attributes['tz_ui'] = new \DateTimeZone('UTC');
        }
        $layout = (isset($attributes['layout'])) ? $attributes['layout'] : 3;
        $dflg = (isset($attributes['dateformat_lg'])) ? $attributes['dateformat_lg'] : 'l jS \of F';
        $dflgend = (isset($attributes['dateformat_lgend'])) ? $attributes['dateformat_lgend'] : '';
        $dftsum = (isset($attributes['dateformat_tsum'])) ? $attributes['dateformat_tsum'] : 'G:i ';
        $dftsend = (isset($attributes['dateformat_tsend'])) ? $attributes['dateformat_tsend'] : '';
        $dftstart = (isset($attributes['dateformat_tstart'])) ? $attributes['dateformat_tstart'] : 'G:i';
        $dftend = (isset($attributes['dateformat_tend'])) ? $attributes['dateformat_tend'] : ' - G:i ';
        $excerptlength = (isset($attributes['excerptlength']) && ' ' < trim($attributes['excerptlength'])) ? (int) $attributes['excerptlength'] : '';
        $attributes['suffix_lg_class'] = self::sanitize_html_clss($attributes['suffix_lg_class']);
        $sflgi = self::sanitize_html_clss($attributes['suffix_lgi_class']);
        $sflgia = self::sanitize_html_clss($attributes['suffix_lgia_class']);
        if (empty($attributes['categories_display'])) {
            $cat_disp = false;
        } else {
            $cat_disp = true;
            $cat_sep = '</small>'.$attributes['categories_display'].'<small>';
        }
        if (! in_array($attributes['tag_sum'], self::$allowed_tags_sum))
            $attributes['tag_sum'] = 'a';
            $ipd = IcsParser::getData($attributes);
            $data = $ipd['data'];
            foreach ($ipd['messages'] as $msg) {
                $secho .= '<!-- ' . $msg . ' -->';
            }
            if (! empty($data) && is_array($data)) {
                $secho .= '<ul id="lg' .$attributes['anchorId'] .'" class="list-group' . $attributes['suffix_lg_class'] . ' simple-ical-widget 270 '. $attributes['title_collapse_toggle'] . '" > ';
                $curdate = '';
                foreach ($data as $e) {
                    $idlist = explode("@", $e->uid,2);
                    $itemid = $attributes['sibid'] . '_' . strval(++ $sn) . '_' . $idlist[0];
                    $evdate = wp_date($dflg, $e->start, $attributes['tz_ui']);
                    $sameday = (wp_date('yz', $e->start, $attributes['tz_ui']) === wp_date('yz', $e->end, $attributes['tz_ui']));
                    $ev_class = ((! empty($e->cal_class)) ? ' ' . sanitize_html_class($e->cal_class) : '');
                    $cat_list = '';
                    if (!empty($e->categories)) {
                        $ev_class = $ev_class . ' ' . implode( ' ', array_map( "sanitize_html_class", $e->categories ));
                        if ($cat_disp) {
                            $cat_list = '<div class="categories"><small>'
                                . implode($cat_sep,str_replace("\n", '<br>', $e->categories ))
                                . '</small></div>';
                        }
                    }
                    if (! $sameday ) {
                        $evdate = str_replace(array(
                            "</div><div>",
                            "</h4><h4>",
                            "</h5><h5>",
                            "</h6><h6>"
                        ), '', $evdate . wp_date($dflgend, $e->end - 1, $attributes['tz_ui']));
                    }
                    $evdtsum = (($e->startisdate === false) ? wp_date($dftsum, $e->start, $attributes['tz_ui']) . wp_date($dftsend, $e->end, $attributes['tz_ui']) : '');
                    if ($layout < 2 && $curdate != $evdate) {
                        if ($curdate != '') {
                            $secho .= '</ul></li>';
                        }
                        $secho .= '<li class="list-group-item' . $sflgi . ' head">' . '<span class="ical-date">' . ucfirst($evdate) . '</span><ul class="list-group' . $attributes['suffix_lg_class'] . '">';
                    }
                    $secho .= '<li class="list-group-item' . $sflgi . $ev_class . '">';
                    if ($layout == 3 && $curdate != $evdate) {
                        $secho .= '<span class="ical-date">' . ucfirst($evdate) . '</span>' . (('a' == $attributes['tag_sum']) ? '<br>' : '');
                    }
 
                    if ('summary' == $attributes['tag_sum']) {
                        $secho .= '<details class="ical_details' . $sflgia . '" id="'. $itemid. '">';
                    }
                    
                    $secho .=  '<' . $attributes['tag_sum'] . ' class="ical_summary' . $sflgia . (('a' == $attributes['tag_sum']) ? '" data-toggle="collapse" data-bs-toggle="collapse" href="#' . $itemid . '" aria-expanded="false" aria-controls="' . $itemid . '">' : '">');
                    if ($layout != 2) {
                        $secho .= $evdtsum;
                    }
                    if (! empty($e->summary)) {
                        $secho .= str_replace("\n", '<br>', $e->summary);
                    }
                    $secho .= '</' . $attributes['tag_sum'] . '>' . $cat_list;
                    if ($layout == 2) {
                        $secho .= '<span>'. $evdate . $evdtsum . '</span>';
                    }

                    if ('summary' != $attributes['tag_sum']) {
                        $secho .= '<div class="ical_details' . $sflgia . (('a' == $attributes['tag_sum']) ? ' collapse' : '') . '" id="'. $itemid. '">';
                    }

                    if (! empty($e->description) && trim($e->description) > '' && $excerptlength !== 0) {
                        if ($excerptlength !== '' && strlen($e->description) > $excerptlength) {
                            $e->description = substr($e->description, 0, $excerptlength + 1);
                            if (rtrim($e->description) !== $e->description) {
                                $e->description = substr($e->description, 0, $excerptlength);
                            } else {
                                if (strrpos($e->description, ' ', max(0, $excerptlength - 10)) !== false or strrpos($e->description, "\n", max(0, $excerptlength - 10)) !== false) {
                                    $e->description = substr($e->description, 0, max(strrpos($e->description, "\n", max(0, $excerptlength - 10)), strrpos($e->description, ' ', max(0, $excerptlength - 10))));
                                } else {
                                    $e->description = substr($e->description, 0, $excerptlength);
                                }
                            }
                        }
                        $e->description = str_replace("\n", '<br>', $e->description);
                        $secho .= '<span class="dsc">'. $e->description. ((strrpos($e->description, '<br>') === (strlen($e->description) - 4)) ? '' : '<br>'). '</span>';
                    }
                    if ($e->startisdate === false && $sameday) {
                        $secho .= '<span class="time">'. wp_date($dftstart, $e->start, $attributes['tz_ui']). '</span><span class="time">'. wp_date($dftend, $e->end, $attributes['tz_ui']). '</span> ';
                    } else {
                        $secho .= '';
                    }
                    if (! empty($e->location)) {
                        $secho .= '<span class="location">'. str_replace("\n", '<br>', $e->location). '</span>';
                    }
                    if ('summary' == $attributes['tag_sum']) {
                        $secho .= '</details></li>';
                    } else {
                        $secho .= '</div></li>';
                    }
                    $curdate = $evdate;
                }
                if ($layout < 2) {
                    $secho .= '</ul></li>';
                }
                $secho .= '</ul>';
                $secho .= $attributes['after_events'];
            } else {
                $secho .= $attributes['no_events'];
            }
            $secho .= '<br class="clear" />';
    }
    /**
     * copied from WP sanitize_html_class, and added space as allowed character to accomodate multiple classes in one string.
     * Strips the string down to A-Z, ,a-z,0-9,_,-. If this results in an empty string then it will return the alternative value supplied.
     *
     * @param string $class
     * @param string $fallback
     * @return string sanitized class or fallback.
     */
    static function sanitize_html_clss( $class, $fallback = '' ) {
        // Strip out any %-encoded octets.
        $sanitized = preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $class );
        
        // Limit to A-Z, ' ', a-z, 0-9, '_', '-'.
        $sanitized = preg_replace( '/[^A-Z a-z0-9_-]/', '', $sanitized );
        
        if ( '' === $sanitized && $fallback ) {
            return  $fallback;
        }
        return $sanitized;
    }
    /**
     * Render the content of the block
     *
     * see
     *
     * @param array $block_attributes
     *            the block attributes (that are changed from default therefore first merged with defaults.)
     * @param array $content
     *            as saved in post by save in ...block.js
     * @param object $block
     *            the bolck that is rendered
     * @return string HTML to render for the block (frontend)
     */
    static function render_block($block_attributes, $content = null, $block = null)
    {
        $block_attributes = array_merge(self::$default_block_attributes, [
            'title' => __('Events', 'simple-google-icalendar-widget'),
            'tzid_ui' => wp_timezone_string()
        ], $block_attributes);
        $block_attributes['anchorId'] = self::sanitize_html_clss($block_attributes['anchorId'], $block_attributes['sibid']);
        if (empty($block_attributes['tzid_ui'])) {
            $block_attributes['tzid_ui'] = wp_timezone_string();
        }
        if (empty($block_attributes['sibid']) && ! empty($block_attributes['blockid'])) {
            $block_attributes['sibid'] = $block_attributes['blockid'];
        }
        if  (empty($block_attributes['tag_title']))  $block_attributes['tag_title'] = 'h3';
        if (!empty($block_attributes['title_collapse_toggle'])){
            $block_attributes['title'] = ('<a data-toggle="collapse" data-bs-toggle="collapse" href="#lg' .$block_attributes['anchorId'] . '" role="button" aria-expanded="'.(('collapse' == $block_attributes['title_collapse_toggle'])?'false':'true').'" aria-controls="collapseMod">' . $block_attributes['title'] . '</a>');
        }
        $titlenode = '<' . $block_attributes['tag_title'] 
            .' class="widget-title block-title" data-sib-t="true">'
            . $block_attributes['title']
            . '</' . $block_attributes['tag_title'] . '>';
            
            $secho = '';
//            $secho .= PHP_EOL . '<!-- ' . PHP_EOL . print_r($block_attributes, true) . PHP_EOL . '-->' . PHP_EOL;
            switch ($block_attributes['wptype']) {
                case 'REST_t':
                    $secho .= $titlenode;
                case 'REST':
                    // Block displayed via REST
                    self::display_block($block_attributes, $secho);
                    break;
                case 'rest_ph':
                    // Placeholder starting point for REST processing display of block.
                    $wrapperattr = (is_wp_version_compatible('5.6')) ? get_block_wrapper_attributes() : '';
                    $secho .= sprintf($block_attributes['before_widget'], ($block_attributes['anchorId'] . '" data-sib-id="' . $block_attributes['sibid'] . '" data-sib-utzui="' . $block_attributes['rest_utzui'] . '" data-sib-st="0-start' ), $wrapperattr);
                    $secho .= $titlenode;
                    $secho .= '<p>';
                    $secho .= __('Processing', 'simple-google-icalendar-widget');
                    $secho .= '</p>' . $block_attributes['after_widget'];
                    try {
                        unset($block_attributes['before_widget'], $block_attributes['after_widget']);
                        self::update_rest_attrs($block_attributes);
                    } catch (\Exception $e) {
                        $secho .= '<p>Caught exception: ' . $e->getMessage() . "</p>\n";
                    }
                    break;
                case 'block':
                case 'ssr':
                    // Block rendered serverside, or in admin via serversiderenderer
                    $wrapperattr = (is_wp_version_compatible('5.6')) ? get_block_wrapper_attributes() : '';
                    $secho .= sprintf($block_attributes['before_widget'], ($block_attributes['anchorId'] . '" data-sib-id="' . $block_attributes['sibid']), $wrapperattr);
                    if (! empty($block_attributes['title'])) {
                        $secho .= $titlenode;
                    }
                    self::display_block($block_attributes, $secho);
                    $secho .= $block_attributes['after_widget'];
                    break;
                default:
                    $secho .= "<!-- unknown wptype:" . $block_attributes['wptype'] . "-->" . PHP_EOL;
            }
            return $secho;
    }
    /**
     * Compare attributes with those in widget option and changed
     * Save attributes in widget option for use in REST call (only when changed on other then excluded keys)
     *
     * @param array $instance
     *            attributes/instance to save $instance['sibid'] is used as (new) key when $w_number is empty.
     * @param string $prev_sibid
     *            Previous save sibid to remeove if sibid is changed.
     * @return when not changed true, succes new value sibid key, else false
     */
    static function update_rest_attrs($instance)
    {
        if (empty($instance)) return false;
        $instances = (get_option(self::SIB_ATTR));
        if (! is_array($instances)) $instances = [];
        
        if (! empty($instance['sibid'])) {
            if (! empty($instance['prev_sibid']) && isset($instances[$instance['prev_sibid']]) && ($instance['sibid'] != $instance['prev_sibid'])) {
                unset($instances[$instance['prev_sibid']]);
            }
            $new_instance = array_diff_assoc(array_merge($instance, self::$exclude_test_attrs), self::$default_block_attributes, self::$exclude_test_attrs);
            if (!empty($instances[$instance['sibid']]) && array_diff_assoc(array_merge($instances[$instance['sibid']], self::$exclude_test_attrs), self::$exclude_test_attrs) == $new_instance){
                return true;
            }
            else {
                $new_instance['saved'] = gmdate('YmdHis');
                $instances[$instance['sibid']] = $new_instance;
                if (update_option(self::SIB_ATTR, $instances, true))
                    return $instance['sibid'];
            }
        }
        return false;
    }
    /**
     * Close html tags in html string
     * @params string $html String with HTML to repair
     * @return string, rpaired HTML string
     */
    static function closetags($html) {
        preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];
        preg_match_all('#</([a-z]+)>#iU', $html, $result);
        $closedtags = $result[1];
        $len_opened = count($openedtags);
        if (count($closedtags) == $len_opened) {
            return $html;
        }
        $openedtags = array_reverse($openedtags);
        for ($i=0; $i < $len_opened; $i++) {
            if (!in_array($openedtags[$i], $closedtags)) {
                $html .= '</'.$openedtags[$i].'>';
            } else {
                unset($closedtags[array_search($openedtags[$i], $closedtags)]);
            }
        }
        return $html;
    }
    /**
     * Block init register block with help of block.json v3 plus
     *
     * @param
     *            .
     */
    static function init_block()
    {
        register_block_type(dirname(__DIR__) . '/block.json', array( )); 
    }
    /**
     * Block init register block with help of block.json
     *
     * @param
     *            .
     */
    static function init_block_v2()
    {
        register_block_type(dirname(__DIR__) . '/v2/block.json', array(
            'attributes' => [
            'wptype' => ['type' => 'string'],
            'sibid' => ['type' => 'string'],
            'prev_sibid' => ['type' => 'string'],
            'postid' => ['type' => 'string'],
            'tzid_ui'=> ['type'=> 'string'],
            'title' => ['type' => 'string', 'default' => __('Events', 'simple-google-icalendar-widget')],
            'calendar_id' => ['type' => 'string', 'default' => ''],
            'event_count' => ['type' => 'integer', 'default' => 10],
            'event_period' => ['type' => 'integer', 'default' => 92],
            'categories_filter_op' => ['type' => 'string', 'enum' => ['','ANY','ALL','NOTANY','NOTALL'], 'default' => ''],
            'categories_filter' => ['type' => 'string', 'default' => ''],
            'categories_display' => ['type' => 'string', 'default' => ''],
            'add_sum_catflt' => ['type' => 'boolean', 'default' => false],    
            'layout' => ['type' => 'integer', 'default' => 3],
            'cache_time' => ['type' => 'integer', 'default' => 60],
            'dateformat_lg' => ['type' => 'string', 'default' => 'l jS \of F'],
            'dateformat_lgend' => ['type' => 'string', 'default' => ''],
            'tag_title' => ['type' => 'string', 'enum' => self::$allowed_tags_sum, 'default' => 'h3'],
            'tag_sum' => ['type' => 'string', 'enum' => self::$allowed_tags_sum, 'default' => 'a'],
            'dateformat_tsum' => ['type' => 'string', 'default' => 'G:i '],
            'dateformat_tsend' => ['type' => 'string', 'default' => ''],
            'dateformat_tstart' => ['type' => 'string', 'default' => 'G:i'],
            'dateformat_tend' => ['type' => 'string', 'default' => ' - G:i '],
            'excerptlength' => ['type' => 'string', ''],
            'suffix_lg_class' => ['type' => 'string', 'default' => ''],
            'suffix_lgi_class' => ['type' => 'string', 'default' => ' py-0'],
            'suffix_lgia_class' => ['type' => 'string', 'default' => ''],
            'after_events' => ['type' => 'string', 'default' => ''],
            'no_events' => ['type' => 'string', 'default' => ''],
            'period_limits' => ['type' => 'string', 'enum' => ['1', '2', '3', '4'], 'default' => '1'],
            'rest_utzui' => ['type' => 'string', 'enum' => ['', '1', '2'], 'default' => ''],
            'clear_cache_now' => ['type' => 'boolean', 'default' => false],
            'anchorId' => ['type' => 'string', 'default' => ''],
            'title_collapse_toggle' => ['type' => 'string', 'enum' => [ '', 'collapse', 'collapse show']],
            'add_collapse_code' => ['type' => 'string', 'default' => ''],
            'blockid' => ['type' => 'string'],
            ],
            'render_callback' => array(
                'WaasdorpSoekhan\WP\Plugin\SimpleGoogleIcalendarWidget\SimpleicalHelper',
                'render_blockv2'
            )
        ));
    }
    /**
     * Render the content of the block v2
     *
     * see
     *
     * @param array $block_attributes
     *            the block attributes (that are changed from default therefore first merged with defaults.)
     * @param array $content
     *            as saved in post by save in ...block.js
     * @param object $block
     *            the block that is rendered
     * @return string escaped HTML to render for the block (frontend)
     */
    static function render_blockv2($block_attributes, $content = null, $block = null)
    {
        return wp_kses(SimpleicalHelper::render_block($block_attributes),'post');
    }
    
    /**
     * Widget init register legacy widget
     *
     * @param
     *            .
     */
    static function simple_ical_widget ()
    {  register_widget( '\WaasdorpSoekhan\WP\Plugin\SimpleGoogleIcalendarWidget\SimpleicalWidget' );
    }
} // end class SimpleicalHelper
