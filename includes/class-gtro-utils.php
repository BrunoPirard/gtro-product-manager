<?php
class GTRO_Asset_Minifier {
    private static $cache_dir = 'cache/';
    
    public static function minify_css($source_file) {
        // Vérifier si on est en développement
        $is_dev = defined('WP_DEBUG') && WP_DEBUG;
        
        // Chemin des fichiers
        $source_path = GTRO_PLUGIN_PATH . 'public/css/' . $source_file;
        $min_path = GTRO_PLUGIN_PATH . 'public/css/' . str_replace('.css', '.min.css', $source_file);
        
        // En développement, utiliser le fichier source
        if ($is_dev) {
            return GTRO_PLUGIN_URL . 'public/css/' . $source_file;
        }
        
        // En production, utiliser/générer le fichier minifié
        if (!file_exists($min_path) || filemtime($source_path) > filemtime($min_path)) {
            $css = file_get_contents($source_path);
            
            // Minification basique
            $css = preg_replace('/\s+/', ' ', $css);
            $css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
            $css = str_replace(array(': ', ' {', '{ ', ', ', '} ', ';}'), array(':','{','{',',','}','}'), $css);
            
            file_put_contents($min_path, $css);
        }
        
        return GTRO_PLUGIN_URL . 'public/css/' . str_replace('.css', '.min.css', $source_file);
    }
}
