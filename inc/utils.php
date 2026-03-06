<?php
/**
 * Utility functions for Quality Audit plugin
 */

/**
 * Convert basic markdown to HTML for display
 * Used for AI suggestions that may contain markdown formatting
 *
 * @param string $text Markdown text
 * @param bool $escape Whether to HTML-escape input first (default: false)
 * @return string HTML output
 */
function qualityaudit_markdown_to_html($text, $escape = false) {
   if (empty($text)) {
      return '';
   }

   if ($escape) {
      $text = Html::clean($text);
   }

   // Bold: **text** or __text__
   $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
   $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

   // Italic: *text* or _text_
   $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
   $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);

   // Line breaks
   $text = nl2br($text);

   // Lists
   $text = preg_replace('/^- (.+)$/m', '<li>$1</li>', $text);
   $text = preg_replace('/^(\d+)\. (.+)$/m', '<li>$2</li>', $text);

   // Wrap consecutive <li> in <ul> (non-greedy to prevent ReDoS)
   $text = preg_replace('|(<li>.*?</li>)\s*(<li>)|', '$1</ul><ul>$2', $text);
   if (preg_match_all('/<li>.*?<\/li>/s', $text, $matches)) {
      $text = '<ul>' . implode('', $matches[0]) . '</ul>';
   }

   return $text;
}
