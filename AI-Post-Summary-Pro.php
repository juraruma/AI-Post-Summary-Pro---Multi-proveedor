<?php
/*
Plugin Name: AI Post Summary Pro
Plugin URI: https://juanrafaelruiz.com/
Description: Sistema profesional multi-proveedor (OpenAI, Claude, Gemini). Prompts y longitudes independientes por IA. Diseño avanzado por tarjetas e instrucciones integradas.
Version: 1.2
Author: Juan Rafael Ruiz
License: GPL2+
Text Domain: aipsp_pro
*/

if (!defined('ABSPATH')) exit;

class AI_Post_Summary_Pro {

    const OPTION_KEY = 'aipsp_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box_data']);
        add_action('wp_ajax_aipsp_generate', [$this, 'ajax_generate_handler']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        add_shortcode('ai_summary_pro', [$this, 'render_shortcode']);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('update_option_' . self::OPTION_KEY, function() {
            delete_transient('aipsp_models_openai');
            delete_transient('aipsp_models_gemini');
        });
    }

    public function get_defaults() {
        return [
            'default_provider' => 'openai',
            // OpenAI
            'openai_key'    => '',
            'openai_model'  => 'gpt-4o-mini',
            'openai_length' => 'medium',
            'openai_prompt' => "Resume este artículo de forma profesional. {{LONGITUD}}\n\nTítulo: {{TITULO}}\n\nContenido:\n{{CONTENIDO}}",
            // Claude
            'claude_key'    => '',
            'claude_model'  => 'claude-3-5-sonnet-20240620',
            'claude_length' => 'medium',
            'claude_prompt' => "Actúa como un editor y resume este texto. {{LONGITUD}}\n\nTítulo: {{TITULO}}\n\nContenido:\n{{CONTENIDO}}",
            // Gemini
            'gemini_key'    => '',
            'gemini_model'  => 'gemini-1.5-flash',
            'gemini_length' => 'medium',
            'gemini_prompt' => "Crea un resumen estructurado del siguiente post. {{LONGITUD}}\n\nTítulo: {{TITULO}}\n\nContenido:\n{{CONTENIDO}}",
            
            // Apariencia Botón
            'btn_txt_closed' => 'Resumir con IA',
            'btn_txt_opened' => 'Ocultar resumen',
            'btn_color' => '#ffffff', 'btn_bg' => '#2271b1', 'btn_bg_hover' => '#135e96', 'btn_bg_active' => '#0a4b78',
            'btn_font_family' => 'inherit', 'btn_font_size' => '14px', 'btn_font_weight' => '600',
            'btn_b_w' => '0px', 'btn_b_s' => 'solid', 'btn_b_c' => '#2271b1', 'btn_b_c_h' => '#135e96', 'btn_radius' => '4px',
            // Contenedor
            'cont_bg' => '#ffffff', 'cont_radius' => '12px',
            'cont_m_t' => '30px', 'cont_m_r' => '0px', 'cont_m_b' => '30px', 'cont_m_l' => '0px',
            'cont_p_t' => '30px', 'cont_p_r' => '30px', 'cont_p_b' => '30px', 'cont_p_l' => '30px',
            'cont_b_w' => '1px', 'cont_b_s' => 'solid', 'cont_b_c' => '#e2e8f0',
            // Header
            'head_tag' => 'h4', 'head_color' => '#1e293b', 'head_font_family' => 'inherit', 'head_font_size' => '22px', 'head_font_weight' => '700', 'head_line_height' => '1.3',
            'head_m_t' => '0px', 'head_m_r' => '0px', 'head_m_b' => '20px', 'head_m_l' => '0px',
            'head_p_t' => '0px', 'head_p_r' => '0px', 'head_p_b' => '15px', 'head_p_l' => '0px',
            'head_b_w' => '2px', 'head_b_s' => 'solid', 'head_b_c' => '#2271b1',
            // Texto
            'text_color' => '#475569', 'text_font_family' => 'inherit', 'text_font_size' => '16px', 'text_font_weight' => '400', 'text_line_height' => '1.7',
        ];
    }

    public function get_settings() {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->get_defaults());
    }

    /* =========================
       HELPERS DE UI
    ========================= */

    private function get_length_options() {
        return [
            'very-short' => 'Muy corto (~1 frase / 30-50 palabras)',
            'short'      => 'Corto (~2-3 frases / 80 palabras)',
            'medium'     => 'Medio (1 párrafo / 150 palabras)',
            'long'       => 'Largo (2-3 párrafos / 300+ palabras)'
        ];
    }

    public function get_google_fonts() {
        return ['inherit' => 'Fuente del Tema', 'Montserrat' => 'Montserrat', 'Roboto' => 'Roboto', 'Open Sans' => 'Open Sans', 'Lato' => 'Lato', 'Poppins' => 'Poppins', 'Oswald' => 'Oswald', 'Playfair Display' => 'Playfair Display'];
    }

    private function render_ui($key, $type = 'text', $options = []) {
        $s = $this->get_settings();
        $name = self::OPTION_KEY . "[$key]";
        $val = esc_attr($s[$key]);

        if ($type === 'select') {
            echo "<select name='$name' style='width:100%'>";
            foreach($options as $k => $v) echo "<option value='$k' ".selected($val,$k,false).">$v</option>";
            echo "</select>";
        } elseif ($type === 'textarea') {
            echo "<textarea name='$name' rows='6' style='width:100%; font-family:monospace; padding:10px;'>$val</textarea>";
            echo "<div style='background:#f0f6fb; border-left:4px solid #118dff; padding:8px 12px; margin-top:5px; font-size:12px;'><strong>Variables:</strong> {{LONGITUD}}, {{TITULO}}, {{CONTENIDO}}</div>";
        } elseif ($type === 'color') {
            echo "<input type='color' name='$name' value='$val' />";
        } else {
            echo "<input type='$type' name='$name' value='$val' style='width:100%' />";
        }
    }

    private function render_box_ui($prefix) {
        $s = $this->get_settings();
        echo "<div style='display:grid; grid-template-columns: repeat(4, 1fr); gap:5px;'>";
        foreach(['t'=>'Top','r'=>'Right','b'=>'Bottom','l'=>'Left'] as $d => $label) {
            $k = "{$prefix}_{$d}";
            echo "<span><small style='display:block'>$label</small><input type='text' name='".self::OPTION_KEY."[$k]' value='".esc_attr($s[$k])."' style='width:100%' /></span>";
        }
        echo "</div>";
    }

    /* =========================
       ADMIN PAGE
    ========================= */

    public function admin_menu() {
        add_options_page('AI Post Summary Pro', 'AI Summary Pro', 'manage_options', 'aipsp-settings', [$this, 'settings_page']);
    }

    public function register_settings() { register_setting('aipsp_group', self::OPTION_KEY); }

    public function settings_page() {
        $s = $this->get_settings();
        ?>
        <style>
            .aipsp-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .aipsp-card h3 { margin-top: 0; border-bottom: 2px solid #f0f0f1; padding-bottom: 10px; color: #1d2327; }
            .aipsp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
            .aipsp-field-group { margin-bottom: 15px; }
            .aipsp-field-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; }
            .aipsp-shortcode-box { background: #f8f9fa; border: 1px dashed #2271b1; padding: 15px; font-family: monospace; font-size: 14px; color: #2271b1; border-radius: 4px; display: inline-block; }
            .tab-content { margin-top: 20px; }
        </style>

        <div class="wrap">
            <h1>AI Post Summary Pro <span style="font-size:12px; vertical-align:middle; background:#0073aa; color:#fff; padding:2px 8px; border-radius:10px;">v1.2</span></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="#tab-openai" class="nav-tab nav-tab-active">OpenAI</a>
                <a href="#tab-claude" class="nav-tab">Claude</a>
                <a href="#tab-gemini" class="nav-tab">Gemini</a>
                <a href="#tab-appearance" class="nav-tab">🎨 Apariencia</a>
                <a href="#tab-info" class="nav-tab">📖 Instrucciones</a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('aipsp_group'); ?>

                <!-- TABS DE PROVEEDORES -->
                <?php foreach(['openai' => 'OpenAI', 'claude' => 'Claude (Anthropic)', 'gemini' => 'Gemini (Google AI)'] as $id => $label): ?>
                <div id="tab-<?php echo $id; ?>" class="tab-content" <?php echo $id !== 'openai' ? 'style="display:none"' : ''; ?>>
                    <div class="aipsp-card">
                        <h3>Configuración de <?php echo $label; ?></h3>
                        <div class="aipsp-field-group">
                            <label>API Key</label>
                            <?php $this->render_ui($id.'_key', 'password'); ?>
                        </div>
                        <div class="aipsp-grid">
                            <div class="aipsp-field-group">
                                <label>Modelo</label>
                                <?php 
                                $models = ($id === 'openai') ? $this->fetch_openai_models($s['openai_key']) : (($id === 'gemini') ? $this->fetch_gemini_models($s['gemini_key']) : $this->get_claude_models());
                                $this->render_ui($id.'_model', 'select', $models); 
                                ?>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Longitud del Resumen para <?php echo $label; ?></label>
                                <?php $this->render_ui($id.'_length', 'select', $this->get_length_options()); ?>
                            </div>
                        </div>
                        <div class="aipsp-field-group">
                            <label>Prompt Personalizado para <?php echo $label; ?></label>
                            <?php $this->render_ui($id.'_prompt', 'textarea'); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- TAB APARIENCIA -->
                <div id="tab-appearance" class="tab-content" style="display:none;">
                    <div class="aipsp-card" style="border-left: 4px solid #0073aa;">
                        <h3>Proveedor Predeterminado</h3>
                        <div class="aipsp-field-group" style="max-width: 400px;">
                            <?php $this->render_ui('default_provider', 'select', ['openai'=>'OpenAI','claude'=>'Claude','gemini'=>'Gemini']); ?>
                            <p class="description">Esta es la IA que responderá al usar el shortcode simple.</p>
                        </div>
                    </div>

                    <div class="aipsp-grid">
                        <div class="aipsp-card">
                            <h3>🕹️ Estilos del Botón</h3>
                            <div class="aipsp-field-group">
                                <label>Textos (Cerrado / Abierto)</label>
                                <?php $this->render_ui('btn_txt_closed'); ?>
                                <div style="margin-top:5px;"><?php $this->render_ui('btn_txt_opened'); ?></div>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Fuente y Peso</label>
                                <?php $this->render_ui('btn_font_family', 'select', $this->get_google_fonts()); ?>
                                <div style="display:flex; gap:5px; margin-top:5px;">
                                    <?php $this->render_ui('btn_font_size'); ?>
                                    <select name="aipsp_settings[btn_font_weight]"><?php foreach(['400','500','600','700','800','900'] as $w) echo "<option value='$w' ".selected($s['btn_font_weight'],$w,false).">$w</option>"; ?></select>
                                </div>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Colores (Texto / Fondo / Hover)</label>
                                <div style="display:flex; gap:5px;">
                                    <?php $this->render_ui('btn_color', 'color'); ?>
                                    <?php $this->render_ui('btn_bg', 'color'); ?>
                                    <?php $this->render_ui('btn_bg_hover', 'color'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="aipsp-card">
                            <h3>📦 Contenedor</h3>
                            <div class="aipsp-field-group">
                                <label>Fondo y Radio</label>
                                <?php $this->render_ui('cont_bg', 'color'); ?>
                                <div style="margin-top:5px;"><?php $this->render_ui('cont_radius'); ?></div>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Márgenes Externos</label><?php $this->render_box_ui('cont_m'); ?>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Rellenos (Padding)</label><?php $this->render_box_ui('cont_p'); ?>
                            </div>
                        </div>

                        <div class="aipsp-card">
                            <h3>🏷️ Título (Header)</h3>
                            <div class="aipsp-field-group">
                                <label>Tag y Color</label>
                                <?php $this->render_ui('head_tag', 'select', ['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','div'=>'DIV','p'=>'P']); ?>
                                <div style="margin-top:5px;"><?php $this->render_ui('head_color', 'color'); ?></div>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Fuente y Borde Inf.</label>
                                <?php $this->render_ui('head_font_family', 'select', $this->get_google_fonts()); ?>
                                <div style="display:flex; gap:5px; margin-top:5px;">
                                    <input type="text" name="aipsp_settings[head_b_w]" value="<?php echo $s['head_b_w']; ?>" style="width:60px" />
                                    <?php $this->render_ui('head_b_c', 'color'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="aipsp-card">
                            <h3>📝 Texto Resumen</h3>
                            <div class="aipsp-field-group">
                                <label>Color y Tamaño</label>
                                <?php $this->render_ui('text_color', 'color'); ?>
                                <div style="margin-top:5px;"><?php $this->render_ui('text_font_size'); ?></div>
                            </div>
                            <div class="aipsp-field-group">
                                <label>Fuente e Interlineado</label>
                                <?php $this->render_ui('text_font_family', 'select', $this->get_google_fonts()); ?>
                                <div style="margin-top:5px;"><?php $this->render_ui('text_line_height'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB INSTRUCCIONES (RECUPERADA) -->
                <div id="tab-info" class="tab-content" style="display:none;">
                    <div class="aipsp-card">
                        <h3>📖 Cómo implementar el resumen</h3>
                        <p>Para mostrar el botón de resumen en tus posts, utiliza el siguiente shortcode:</p>
                        <div class="aipsp-shortcode-box">[ai_summary_pro]</div>
                        <p style="margin-top:20px;"><strong>Uso avanzado:</strong> Puedes forzar un proveedor específico ignorando el predeterminado:</p>
                        <ul style="list-style:disc; margin-left:20px;">
                            <li><code>[ai_summary_pro provider="openai"]</code></li>
                            <li><code>[ai_summary_pro provider="claude"]</code></li>
                            <li><code>[ai_summary_pro provider="gemini"]</code></li>
                        </ul>
                    </div>
                    
                    <div class="aipsp-card">
                        <h3>💡 Consejos de uso</h3>
                        <p><strong>1. Generación automática:</strong> El resumen se genera la primera vez que un usuario hace clic en el botón. Si ya existe en la base de datos, se carga instantáneamente sin gastar tokens.</p>
                        <p><strong>2. Edición manual:</strong> En la pantalla de edición del Post, verás una caja llamada "AI Post Summary Pro". Allí puedes revisar o modificar el texto generado por la IA antes o después de que se publique.</p>
                        <p><strong>3. Prompts:</strong> Recuerda que Claude suele ser mejor para textos creativos y Gemini para resúmenes rápidos y técnicos. ¡Prueba cuál te gusta más!</p>
                    </div>
                </div>

                <?php submit_button('Guardar todos los ajustes'); ?>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($){
                $('.nav-tab').click(function(e){
                    e.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active'); $(this).addClass('nav-tab-active');
                    $('.tab-content').hide(); $($(this).attr('href')).show();
                });
            });
        </script>
        <?php
    }

    /* =========================
       LOGICA DE MODELOS Y API
    ========================= */

    private function fetch_openai_models($key) {
        if (!$key) return [];
        $cached = get_transient('aipsp_models_openai');
        if ($cached) return $cached;
        $res = wp_remote_get('https://api.openai.com/v1/models', ['headers' => ['Authorization' => 'Bearer '.$key]]);
        if (is_wp_error($res)) return [];
        $body = json_decode(wp_remote_retrieve_body($res), true);
        $models = [];
        if (isset($body['data'])) foreach($body['data'] as $m) if(strpos($m['id'], 'gpt-') === 0) $models[$m['id']] = $m['id'];
        ksort($models);
        set_transient('aipsp_models_openai', $models, DAY_IN_SECONDS);
        return $models;
    }

    private function fetch_gemini_models($key) {
        if (!$key) return [];
        $cached = get_transient('aipsp_models_gemini');
        if ($cached) return $cached;
        $res = wp_remote_get("https://generativelanguage.googleapis.com/v1beta/models?key=$key");
        if (is_wp_error($res)) return [];
        $body = json_decode(wp_remote_retrieve_body($res), true);
        $models = [];
        if (isset($body['models'])) foreach($body['models'] as $m) {
            $id = str_replace('models/', '', $m['name']);
            if (strpos($id, 'gemini-') === 0) $models[$id] = $m['displayName'];
        }
        set_transient('aipsp_models_gemini', $models, DAY_IN_SECONDS);
        return $models;
    }

    private function get_claude_models() {
        return ['claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet', 'claude-3-opus-20240229' => 'Claude 3 Opus', 'claude-3-haiku-20240307' => 'Claude 3 Haiku'];
    }

    public function fetch_ai_summary($post_id, $provider = 'default') {
        $s = $this->get_settings();
        if ($provider === 'default') $provider = $s['default_provider'];
        
        $post = get_post($post_id);
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        if (mb_strlen($content) > 10000) $content = mb_substr($content, 0, 10000);

        $prompt_template = $s["{$provider}_prompt"];
        $length_val = $this->get_length_options()[$s["{$provider}_length"]];
        $prompt = str_replace(['{{TITULO}}','{{CONTENIDO}}','{{LONGITUD}}'], [$post->post_title, $content, $length_val], $prompt_template);

        switch ($provider) {
            case 'openai': return $this->call_openai($s['openai_key'], $s['openai_model'], $prompt);
            case 'claude': return $this->call_claude($s['claude_key'], $s['claude_model'], $prompt);
            case 'gemini': return $this->call_gemini($s['gemini_key'], $s['gemini_model'], $prompt);
        }
        return new WP_Error('error', 'Proveedor no válido');
    }

    private function call_openai($key, $model, $prompt) {
        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer '.$key, 'Content-Type' => 'application/json'],
            'body' => json_encode(['model' => $model, 'messages' => [['role'=>'user', 'content'=>$prompt]]])
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        return $body['choices'][0]['message']['content'] ?? new WP_Error('fail', 'Error OpenAI');
    }

    private function call_claude($key, $model, $prompt) {
        $res = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => ['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json'],
            'body' => json_encode(['model' => $model, 'max_tokens' => 1024, 'messages' => [['role'=>'user', 'content'=>$prompt]]])
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        return $body['content'][0]['text'] ?? new WP_Error('fail', 'Error Claude');
    }

    private function call_gemini($key, $model, $prompt) {
        $res = wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['contents' => [['parts' => [['text' => $prompt]]]]])
        ]);
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        return $body['candidates'][0]['content']['parts'][0]['text'] ?? new WP_Error('fail', 'Error Gemini');
    }

    /* =========================
       ASSETS Y CSS
    ========================= */

    public function enqueue_frontend_assets() {
        if (!is_singular('post')) return;
        $s = $this->get_settings();
        $fonts = array_unique([$s['btn_font_family'], $s['head_font_family'], $s['text_font_family']]);
        $load = [];
        foreach($fonts as $f) if($f !== 'inherit') $load[] = str_replace(' ', '+', $f) . ':400,600,700,900';
        if ($load) wp_enqueue_style('aipsp-fonts', 'https://fonts.googleapis.com/css?family=' . implode('|', $load));

        wp_register_script('aipsp-js', false, [], '1.2', true);
        wp_enqueue_script('aipsp-js');
        wp_add_inline_script('aipsp-js', "var AIPSP_API = '".esc_url_raw(rest_url('aipsp/v1/summarize'))."'; " . $this->get_js_code());
        wp_add_inline_style('wp-block-library', $this->generate_css($s));
    }

    private function generate_css($s) {
        $f_btn = $s['btn_font_family'] === 'inherit' ? 'inherit' : "'{$s['btn_font_family']}'";
        $f_head = $s['head_font_family'] === 'inherit' ? 'inherit' : "'{$s['head_font_family']}'";
        $f_text = $s['text_font_family'] === 'inherit' ? 'inherit' : "'{$s['text_font_family']}'";
        return "
        .aipsp-btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 12px 28px; cursor: pointer; transition: 0.3s;
            font-family: $f_btn; font-size: {$s['btn_font_size']}; font-weight: {$s['btn_font_weight']};
            color: {$s['btn_color']}; background-color: {$s['btn_bg']};
            border: {$s['btn_b_w']} solid {$s['btn_b_c']}; border-radius: {$s['btn_radius']};
        }
        .aipsp-btn:hover { background-color: {$s['btn_bg_hover']}; border-color: {$s['btn_b_c_h']}; }
        .aipsp-res {
            display: none; box-sizing: border-box; background: {$s['cont_bg']};
            margin: {$s['cont_m_t']} {$s['cont_m_r']} {$s['cont_m_b']} {$s['cont_m_l']};
            padding: {$s['cont_p_t']} {$s['cont_p_r']} {$s['cont_p_b']} {$s['cont_p_l']};
            border: {$s['cont_b_w']} solid {$s['cont_b_c']}; border-radius: {$s['cont_radius']};
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .aipsp-head {
            color: {$s['head_color']}; font-family: $f_head; font-size: {$s['head_font_size']}; font-weight: {$s['head_font_weight']}; line-height: {$s['head_line_height']};
            margin: {$s['head_m_t']} {$s['head_m_r']} {$s['head_m_b']} {$s['head_m_l']};
            padding: {$s['head_p_t']} {$s['head_p_r']} {$s['head_p_b']} {$s['head_p_l']};
            border-bottom: {$s['head_b_w']} solid {$s['head_b_c']};
        }
        .aipsp-content { color: {$s['text_color']}; font-family: $f_text; font-size: {$s['text_font_size']}; line-height: {$s['text_line_height']}; }";
    }

    public function render_shortcode($atts) {
        global $post; if(!$post || $post->post_type !== 'post') return '';
        $s = $this->get_settings();
        $a = shortcode_atts(['provider' => 'default'], $atts);
        $prov = $a['provider'] === 'default' ? $s['default_provider'] : $a['provider'];
        $summary = get_post_meta($post->ID, "_aipsp_res_$prov", true);
        ob_start();
        ?>
        <div class="aipsp-wrap" data-post-id="<?php echo $post->ID; ?>" data-provider="<?php echo $prov; ?>" data-closed="<?php echo esc_attr($s['btn_txt_closed']); ?>" data-opened="<?php echo esc_attr($s['btn_txt_opened']); ?>">
            <button class="aipsp-btn" onclick="aipspToggle(this)"><span class="aipsp-txt"><?php echo esc_html($s['btn_txt_closed']); ?></span></button>
            <div class="aipsp-res">
                <<?php echo $s['head_tag']; ?> class="aipsp-head"><?php echo get_the_title($post->ID); ?></<?php echo $s['head_tag']; ?>>
                <div class="aipsp-content"><?php echo $summary ? wpautop($summary) : ''; ?></div>
                <div class="aipsp-loader" style="display:none; font-style:italic; margin-top:10px;">Generando resumen...</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_js_code() {
        return "function aipspToggle(btn){
            var wrap=btn.closest('.aipsp-wrap'), res=wrap.querySelector('.aipsp-res'), content=res.querySelector('.aipsp-content'), loader=res.querySelector('.aipsp-loader'), txt=btn.querySelector('.aipsp-txt');
            if(res.style.display==='block'){ res.style.display='none'; txt.textContent=wrap.dataset.closed; }
            else {
                res.style.display='block'; txt.textContent=wrap.dataset.opened;
                if(content.innerHTML.trim()===''){
                    loader.style.display='block';
                    fetch(AIPSP_API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:wrap.dataset.postId,provider:wrap.dataset.provider})})
                    .then(r=>r.json()).then(data=>{
                        loader.style.display='none';
                        if(data.success) content.innerHTML=data.summary;
                        else content.innerHTML='<p style=\"color:red\">'+data.message+'</p>';
                    });
                }
            }
        }";
    }

    /* =========================
       METABOX ADMIN
    ========================= */

    public function add_meta_box() {
        $s = $this->get_settings();
        if ($s['openai_key'] || $s['claude_key'] || $s['gemini_key'])
            add_meta_box('aipsp_meta', 'AI Post Summary Pro', [$this, 'render_metabox'], 'post', 'normal', 'high');
    }

    public function render_metabox($post) {
        $s = $this->get_settings(); wp_nonce_field('aipsp_nonce_act', 'aipsp_nonce');
        foreach(['openai','claude','gemini'] as $p) {
            if(!$s[$p.'_key']) continue;
            $val = get_post_meta($post->ID, "_aipsp_res_$p", true);
            echo "<div style='margin-bottom:15px;'><label><strong>Resumen $p:</strong></label><textarea name='aipsp_res_$p' style='width:100%' rows='4'>".esc_textarea($val)."</textarea>
            <button type='button' class='button aipsp-gen-btn' data-provider='$p' data-post-id='{$post->ID}'>⚡ Generar $p</button></div>";
        }
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['aipsp_nonce']) || !wp_verify_nonce($_POST['aipsp_nonce'], 'aipsp_nonce_act')) return;
        foreach(['openai','claude','gemini'] as $p) if(isset($_POST["aipsp_res_$p"])) update_post_meta($post_id, "_aipsp_res_$p", wp_kses_post($_POST["aipsp_res_$p"]));
    }

    public function ajax_generate_handler() {
        check_ajax_referer('aipsp_admin_nonce', 'nonce');
        $res = $this->fetch_ai_summary($_POST['post_id'], $_POST['provider']);
        if(is_wp_error($res)) wp_send_json_error($res->get_error_message());
        update_post_meta($_POST['post_id'], "_aipsp_res_".$_POST['provider'], $res);
        wp_send_json_success(['summary' => $res]);
    }

    public function enqueue_admin_assets($hook) {
        if(!in_array($hook,['post.php','post-new.php'])) return;
        wp_enqueue_script('aipsp-admin', false, ['jquery'], '1.2', true);
        $nonce = wp_create_nonce('aipsp_admin_nonce');
        wp_add_inline_script('aipsp-admin', "jQuery('.aipsp-gen-btn').click(function(){
            var b=jQuery(this), p=b.data('provider'), pid=b.data('post-id');
            b.prop('disabled',true).text('...');
            jQuery.post(ajaxurl,{action:'aipsp_generate',post_id:pid,provider:p,nonce:'$nonce'},function(r){
                if(r.success) jQuery('textarea[name=\"aipsp_res_'+p+'\"]').val(r.data.summary);
                b.prop('disabled',false).text('⚡ Generar '+p);
            });
        });");
    }

    public function register_rest_routes() {
        register_rest_route('aipsp/v1', '/summarize', [
            'methods' => 'POST',
            'callback' => function($request) {
                $pid = intval($request->get_param('post_id'));
                $prov = sanitize_text_field($request->get_param('provider'));
                $res = $this->fetch_ai_summary($pid, $prov);
                if(is_wp_error($res)) return ['success'=>false, 'message'=>$res->get_error_message()];
                update_post_meta($pid, "_aipsp_res_$prov", $res);
                return ['success'=>true, 'summary'=>wpautop($res)];
            },
            'permission_callback' => '__return_true'
        ]);
    }
}
new AI_Post_Summary_Pro();