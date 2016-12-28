<?php
/*
 * Plugin Name: Weather Currency Widget
 * Description: Display temperature in Kyiv and currency rate USD/UAH
 * Version: 1
 * Author: lmn
 * Text Domain: wcw
 *
 */

/**
 * Data for openweathermap api
 */

class W_cw {
    public function getWeather($id=703448) {
        $data = new stdClass();
        if($id) {
            try {
                $url = 'http://api.openweathermap.org/data/2.5/weather?id='.$id.'&units=metric&APPID=85945671974d6459ee3b2841380f5bda';
                $ch = curl_init();
                $timeout = 5;
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
                $data = curl_exec($ch);
                $data = json_decode(utf8_encode($data));
                $data->error = false;
                if($data->cod != "200") {
                    $data->error = __("Failed retrieving weather data.", 'wcw');
                } else {
                    $main = $data->main;
                    $data->tempC = round(($main->temp -273.15), 0);
                    $data->tempF = round(((($data->tempC * 9 ) / 5) + 32) ,0);
                }

            } catch(Exception $ex) {
                $data->error = $ex->getMessage();
            }

        } else {
            $data->error = __("API-key is not valid, problem with connection to openweathermap.org!", 'wcw');
        }
        return $data;
    }
}
class C_cw {
    public function getCurrency() {
        $data_c = new stdClass();


            try {
                $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json';
                $ch = curl_init();
                $timeout = 5;
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
                $data_c = curl_exec($ch);
                $data_c = json_decode(utf8_encode($data_c));
//                $data_c->error->value = false;

            } catch(Exception $ex) {
                $data_c->error = $ex->getMessage();
            }


        return $data_c;

    }
}
/**
 * Class weather_currency_widget for rendering weather
 */
class weather_currency_widget extends WP_Widget
{
    var $id = "weather_currency_widget";
    function weather_currency_widget(){
        
        parent::__construct('weather_currency_widget', 'Weather Currency Widget', 
            array('description' => __('Sample widget to show temperature and currency rate', 'wcw'),)

        );
    }
    
    function widget($args, $settings)
    {
        //display Frontend
        
        $settings['wcw_city_name'] = 'Kyiv';

        if (get_option( 'weather_currency_renew_last' ) + $settings['wcw_renewAfter'] * 60 < time())
        {
            //renew!
            $wcw = new W_cw();
            $wcw_data = $wcw->getWeather($settings['wcw_id']);

            $ccw = new C_cw();
            $ccw_data = $ccw->getCurrency();

            if (!$wcw_data->error && !$ccw_data->error)
            {
                update_option( 'weather_currency_renew_last', time() );
                update_option( 'weather_currency_openweathermap', serialize($wcw_data) );

                update_option( 'weather_currency_nbu', serialize($ccw_data) );
            }
        } else
        {
            //cache
            $wcw_data = unserialize ( get_option( 'weather_currency_openweathermap' ));

            $ccw_data = unserialize ( get_option( 'weather_currency_nbu' ));
        }


        if ($settings['wcw_fontColor']) $wcw_fontColor = ' color: ' . $settings['wcw_fontColor']; else $wcw_fontColor = '';
        //
        //draw widget
        //
        echo $args['before_widget'];
        echo $args['before_title'];
        echo $settings['wcw_city_name'];
        echo $args['after_title'];
        if ($wcw_data->error ===false && $ccw_data->error ===false) {
            echo '<div class="wi wi-owm-' . $wcw_data->weather[0]->id .'" title="' . $wcw_data->weather[0]->description . '" style="font-size: 2em;' . $wcw_fontColor . '">
		       <span class="wcw_temp">' . $wcw_data->tempC . '&deg;</span>
		     </div>
		     <div style="font-size: 2em">' . '<span>' . $ccw_data[60]->text . '</span>' . $ccw_data[60]->rate . '</div>';
        } else {
            echo $wcw_data->error;
        }
        echo $args['after_widget'];
    }

    function form($instance)
    {
        //Backend
        if (isset($instance['wcw_renewAfter'])) { $wcw_renewAfter = $instance['wcw_renewAfter']; } else { $wcw_renewAfter = '30'; }
        if (isset($instance['wcw_fontColor'])) { $wcw_fontColor = $instance['wcw_fontColor']; } else { $wcw_fontColor = ''; }
        $wcw_value =  array('C' => '', 'F' => '');
        if (isset($instance['wcw_value'])) { $wcw_value[$instance['wcw_value']] = ' checked'; }


        echo __("degrees in: ", 'wcw') .'<br /><input type="radio" name="' . $this->get_field_name('wcw_value') . '" id="' . $this->get_field_name('wcw_value') . '" value="C" ' . $wcw_value['C'] . ' />°C / 
		<input type="radio" name="' . $this->get_field_name('wcw_value') . '" id="' . $this->get_field_name('wcw_value') . '" value="F" ' . $wcw_value['F'] . ' />°F
		<br />';
        echo __("font color (standard: empty):", 'wcw') .'<br /><input type="text" name="' . $this->get_field_name('wcw_fontColor') . '" id="' . $this->get_field_name('wcw_fontColor') . '" style="width: 80px;" value="' . $wcw_fontColor . '" /><br /><br />';
        echo __("interval:", 'wcw') .'<br /><input type="text" name="' . $this->get_field_name('wcw_renewAfter') . '" id="' . $this->get_field_name('wcw_renewAfter') . '" style="width: 40px;" value="' . $wcw_renewAfter . '" />min<br /><br />';
        echo __("last update: ", 'wcw') . ' ' . date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), get_option( 'weather_currency_renew_last' ) + (get_option('gmt_offset') * 3600));
    }
    
    function update($new_instance, $old_instance)
    {
        
            //Admin save
            $instance = array();
        
            $instance['wcw_renewAfter'] = $new_instance['wcw_renewAfter'];
            $instance['wcw_fontColor'] = $new_instance['wcw_fontColor'];
            $instance['wcw_value'] = $new_instance['wcw_value'];

            return $instance;

        
    }



    public static function widget_init()
    {

        register_widget("weather_currency_widget");

    } 	  	

    public static function wp_enqueue_style()
    {

        wp_enqueue_style( 'wcw', plugins_url('css/weather-icons.min.css', __FILE__) );

    }   	

} 

add_action('wp_enqueue_scripts', array('weather_currency_widget' , 'wp_enqueue_style'));
add_action('widgets_init', array('weather_currency_widget','widget_init'));

?>