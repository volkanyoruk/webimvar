<?php
declare(strict_types=1);

class TemplateEngine
{
    /**
     * Basit şablon motoru: $vars değişkenlerini çıkarır, $template'i işler.
     * $layout verilirse, $content değişkeniyle layout içine gömer.
     */
    public static function render(string $template, array $vars = [], ?string $layout = null): void
    {
        if (!is_file($template)) {
            throw new RuntimeException("Template bulunamadı: $template");
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        include $template;
        $content = ob_get_clean();

        if ($layout) {
            if (!is_file($layout)) {
                throw new RuntimeException("Layout bulunamadı: $layout");
            }
            include $layout;
        } else {
            echo $content;
        }
    }
}