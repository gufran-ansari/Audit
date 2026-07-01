<?php

namespace App\Modules\FaqSchemaAudit;

/**
 * FAQ Schema Audit Processor
 * 
 * Processes HTML and detects FAQ schema and content
 */
class FaqSchemaAuditProcessor
{
    private const TIMEOUT = 30; // seconds
    private const MAX_REDIRECTS = 5;
    private const USER_AGENT = 'AuditWithGufran/1.0 (SEO Audit Bot)';

    /**
     * Fetch and process URL for FAQ schema audit
     */
    public function process(string $url): array
    {
        // Fetch HTML
        $html = $this->fetchUrl($url);

        if ($html === false) {
            return [
                'status' => 'ERROR',
                'has_faq_schema' => false,
                'has_faq_content' => false,
                'schema_type' => null,
                'schema_count' => 0,
                'question_count' => 0,
                'schema_snippets' => [],
                'error' => 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($html);
    }

    /**
     * Process pre-fetched HTML for FAQ schema audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Detect FAQ schema
            $schemaData = $this->detectFaqSchema($html);

            // Detect FAQ content patterns
            $hasFaqContent = $this->detectFaqContent($html);

            // Determine overall status
            $status = $this->determineStatus(
                $schemaData['has_schema'],
                $hasFaqContent
            );

            return [
                'status' => $status,
                'has_faq_schema' => $schemaData['has_schema'],
                'has_faq_content' => $hasFaqContent,
                'schema_type' => $schemaData['schema_type'],
                'schema_count' => $schemaData['schema_count'],
                'question_count' => $schemaData['question_count'],
                'schema_snippets' => $schemaData['snippets'],
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("FAQ Schema Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'has_faq_schema' => false,
                'has_faq_content' => false,
                'schema_type' => null,
                'schema_count' => 0,
                'question_count' => 0,
                'schema_snippets' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch URL content
     */
    private function fetchUrl(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept all encodings
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $httpCode < 200 || $httpCode >= 400) {
            logMessage("Failed to fetch URL $url: HTTP $httpCode, Error: $error", 'warning');
            return false;
        }

        return $content;
    }

    /**
     * Detect FAQ schema in page
     */
    private function detectFaqSchema(string $html): array
    {
        $hasSchema = false;
        $schemaType = null;
        $schemaCount = 0;
        $questionCount = 0;
        $snippets = [];

        // Look for JSON-LD scripts
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonLd) {
                $data = json_decode($jsonLd, true);
                
                if ($data && $this->isFaqSchema($data)) {
                    $hasSchema = true;
                    $schemaCount++;
                    $schemaType = $data['@type'] ?? 'FAQPage';
                    
                    // Count questions
                    if (isset($data['mainEntity']) && is_array($data['mainEntity'])) {
                        $questionCount += count($data['mainEntity']);
                        
                        // Extract limited snippets (first 2 questions)
                        $limit = min(2, count($data['mainEntity']));
                        for ($i = 0; $i < $limit; $i++) {
                            $question = $data['mainEntity'][$i];
                            if (isset($question['name'])) {
                                $snippets[] = substr($question['name'], 0, 100);
                            }
                        }
                    }
                }
            }
        }

        return [
            'has_schema' => $hasSchema,
            'schema_type' => $schemaType,
            'schema_count' => $schemaCount,
            'question_count' => $questionCount,
            'snippets' => $snippets
        ];
    }

    /**
     * Check if data is FAQ schema
     */
    private function isFaqSchema($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        // Check @type
        if (isset($data['@type'])) {
            $type = $data['@type'];
            
            // Direct match
            if ($type === 'FAQPage') {
                return true;
            }
            
            // Array of types
            if (is_array($type) && in_array('FAQPage', $type)) {
                return true;
            }
        }

        // Check for mainEntity with Question type
        if (isset($data['mainEntity']) && is_array($data['mainEntity'])) {
            foreach ($data['mainEntity'] as $entity) {
                if (isset($entity['@type']) && $entity['@type'] === 'Question') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect FAQ content patterns (without schema)
     */
    private function detectFaqContent(string $html): bool
    {
        // Remove scripts and styles to avoid false positives
        $cleanHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $cleanHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanHtml);
        
        // Convert to lowercase for case-insensitive matching
        $lowerHtml = strtolower($cleanHtml);
        
        // Look for FAQ indicators
        $faqIndicators = [
            'frequently asked questions',
            'faq',
            'questions and answers',
            'q&a',
            'common questions'
        ];
        
        foreach ($faqIndicators as $indicator) {
            if (strpos($lowerHtml, $indicator) !== false) {
                // Found FAQ indicator, now check for question patterns
                if ($this->hasQuestionPatterns($cleanHtml)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check for question patterns in HTML
     */
    private function hasQuestionPatterns(string $html): bool
    {
        // Look for common question patterns
        $patterns = [
            '/\bwhat\s+is\b/i',
            '/\bhow\s+to\b/i',
            '/\bhow\s+do\b/i',
            '/\bwhy\s+is\b/i',
            '/\bwhen\s+should\b/i',
            '/\bcan\s+i\b/i',
            '/\?\s*<\//',  // Questions ending with ?
        ];
        
        $questionCount = 0;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $questionCount += count($matches[0]);
            }
        }
        
        // If we found 3+ question patterns, likely has FAQ content
        return $questionCount >= 3;
    }

    /**
     * Determine overall status
     */
    private function determineStatus(bool $hasSchema, bool $hasContent): string
    {
        // PASS if FAQ schema found
        if ($hasSchema) {
            return 'PASS';
        }

        // WARNING if FAQ content found but no schema
        if ($hasContent) {
            return 'WARNING';
        }

        // FAIL if expected FAQ not found (page likely doesn't have FAQs)
        // Note: This is informational, not necessarily a failure
        return 'PASS'; // Changed to PASS since not having FAQ is not a failure
    }

    /**
     * Validate result data
     */
    public function validateResult(array $result): bool
    {
        return isset($result['status']) 
            && isset($result['has_faq_schema']) 
            && in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR']);
    }
}
