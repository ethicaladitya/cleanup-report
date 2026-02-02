<?php
/**
 * Simple PDF Generator
 * Creates PDF files without external dependencies
 */

class CRG_Simple_PDF {
    
    private $objects = [];
    private $object_count = 0;
    private $pages = [];
    private $current_page = 0;
    private $fonts = [];
    
    public function __construct() {
        $this->add_font('Helvetica');
    }
    
    /**
     * Add a new page
     */
    public function add_page() {
        $this->current_page++;
        $this->pages[$this->current_page] = [];
        return $this->current_page;
    }
    
    /**
     * Add text to current page
     */
    public function add_text($x, $y, $text, $font_size = 12, $font = 'Helvetica') {
        if (!isset($this->pages[$this->current_page])) {
            $this->add_page();
        }
        
        $this->pages[$this->current_page][] = [
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'font_size' => $font_size,
            'font' => $font
        ];
    }
    
    /**
     * Add a line
     */
    public function add_line($x1, $y1, $x2, $y2) {
        if (!isset($this->pages[$this->current_page])) {
            $this->add_page();
        }
        
        $this->pages[$this->current_page][] = [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2
        ];
    }
    
    /**
     * Add font definition
     */
    private function add_font($name) {
        $this->fonts[$name] = ++$this->object_count;
        $this->objects[$this->object_count] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$name} >>";
    }
    
    /**
     * Generate PDF content
     */
    public function output($filename = '', $dest = 'I') {
        $pdf = $this->generate_pdf();
        
        if ($dest === 'D') {
            // Download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
        } elseif ($dest === 'S') {
            // Return as string
            return $pdf;
        } else {
            // Display inline
            header('Content-Type: application/pdf');
            echo $pdf;
        }
    }
    
    /**
     * Generate the PDF structure
     */
    private function generate_pdf() {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        
        // Catalog
        $catalog_obj = ++$this->object_count;
        $pages_obj = ++$this->object_count;
        
        // Page objects
        $page_objects = [];
        foreach ($this->pages as $page_num => $page_content) {
            $page_objects[$page_num] = ++$this->object_count;
            $content_obj = ++$this->object_count;
            
            // Generate page content stream
            $stream = $this->generate_page_content($page_content);
            $this->objects[$content_obj] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            
            // Page object
            $this->objects[$page_objects[$page_num]] = "<< /Type /Page /Parent {$pages_obj} 0 R /MediaBox [0 0 612 792] /Resources << /Font << " . $this->get_font_references() . " >> >> /Contents {$content_obj} 0 R >>";
        }
        
        // Pages object
        $kids = implode(' ', array_map(function($obj) { return "{$obj} 0 R"; }, $page_objects));
        $this->objects[$pages_obj] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($this->pages) . " >>";
        
        // Catalog object
        $this->objects[$catalog_obj] = "<< /Type /Catalog /Pages {$pages_obj} 0 R >>";
        
        // Write objects
        foreach ($this->objects as $obj_num => $obj_content) {
            $offsets[$obj_num] = strlen($pdf);
            $pdf .= "{$obj_num} 0 obj\n{$obj_content}\nendobj\n";
        }
        
        // Write font objects
        foreach ($this->fonts as $font_name => $font_obj) {
            if (!isset($offsets[$font_obj])) {
                $offsets[$font_obj] = strlen($pdf);
                $pdf .= "{$font_obj} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /{$font_name} >>\nendobj\n";
            }
        }
        
        // Cross-reference table
        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . ($this->object_count + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        
        for ($i = 1; $i <= $this->object_count; $i++) {
            $offset = isset($offsets[$i]) ? $offsets[$i] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        
        // Trailer
        $pdf .= "trailer\n<< /Size " . ($this->object_count + 1) . " /Root {$catalog_obj} 0 R >>\n";
        $pdf .= "startxref\n{$xref_offset}\n%%EOF";
        
        return $pdf;
    }
    
    /**
     * Generate content for a page
     */
    private function generate_page_content($page_content) {
        $stream = "BT\n";
        $current_font = '';
        $current_size = 0;
        
        foreach ($page_content as $item) {
            if ($item['type'] === 'text') {
                // Set font only if it changed
                if ($current_font !== $item['font'] || $current_size !== $item['font_size']) {
                    $stream .= "/{$item['font']} {$item['font_size']} Tf\n";
                    $current_font = $item['font'];
                    $current_size = $item['font_size'];
                }
                
                // Move to position and show text
                $stream .= "{$item['x']} {$item['y']} Td\n";
                $stream .= "(" . $this->escape_string($item['text']) . ") Tj\n";
                
                // Reset text position to origin for next text
                $stream .= "-{$item['x']} -{$item['y']} Td\n";
            }
        }
        
        $stream .= "ET\n";
        
        // Add lines after text
        foreach ($page_content as $item) {
            if ($item['type'] === 'line') {
                $stream .= "{$item['x1']} {$item['y1']} m {$item['x2']} {$item['y2']} l S\n";
            }
        }
        
        return $stream;
    }
    
    /**
     * Get font references for resources
     */
    private function get_font_references() {
        $refs = [];
        foreach ($this->fonts as $font_name => $font_obj) {
            $refs[] = "/{$font_name} {$font_obj} 0 R";
        }
        return implode(' ', $refs);
    }
    
    /**
     * Escape special characters in strings
     */
    private function escape_string($str) {
        return addcslashes($str, "()\\");
    }
}