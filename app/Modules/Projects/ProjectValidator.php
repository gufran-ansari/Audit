<?php

namespace App\Modules\Projects;

/**
 * Project Validator
 * 
 * Validates project data
 */
class ProjectValidator
{
    private $errors = [];

    /**
     * Validate project creation data
     */
    public function validateCreate(array $data, ProjectRepository $repository): bool
    {
        $this->errors = [];

        // Validate project name
        if (empty($data['project_name'])) {
            $this->errors['project_name'] = 'Project name is required';
        } elseif (strlen($data['project_name']) < 3) {
            $this->errors['project_name'] = 'Project name must be at least 3 characters';
        } elseif (strlen($data['project_name']) > 255) {
            $this->errors['project_name'] = 'Project name must not exceed 255 characters';
        }

        // Validate domain
        if (empty($data['primary_domain'])) {
            $this->errors['primary_domain'] = 'Domain or Sitemap URL is required';
        } else {
            $domain = $this->extractDomain($data['primary_domain']);
            
            if (!$domain) {
                $this->errors['primary_domain'] = 'Please enter a valid domain or URL';
            } else {
                // Check if domain already exists
                $normalizedDomain = $this->normalizeDomain($domain);
                if ($repository->domainExists($normalizedDomain)) {
                    $this->errors['primary_domain'] = 'A project with this domain already exists';
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate project update data
     */
    public function validateUpdate(array $data, int $projectId, ProjectRepository $repository): bool
    {
        $this->errors = [];

        // Validate project name if provided
        if (isset($data['project_name'])) {
            if (empty($data['project_name'])) {
                $this->errors['project_name'] = 'Project name is required';
            } elseif (strlen($data['project_name']) < 3) {
                $this->errors['project_name'] = 'Project name must be at least 3 characters';
            } elseif (strlen($data['project_name']) > 255) {
                $this->errors['project_name'] = 'Project name must not exceed 255 characters';
            }
        }

        // Validate domain if provided
        if (isset($data['primary_domain'])) {
            if (empty($data['primary_domain'])) {
                $this->errors['primary_domain'] = 'Domain is required';
            } else {
                $domain = $this->extractDomain($data['primary_domain']);
                
                if (!$domain) {
                    $this->errors['primary_domain'] = 'Please enter a valid domain or URL';
                } else {
                    // Check if domain already exists (excluding current project)
                    $normalizedDomain = $this->normalizeDomain($domain);
                    if ($repository->domainExists($normalizedDomain, $projectId)) {
                        $this->errors['primary_domain'] = 'A project with this domain already exists';
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Extract domain from URL or domain string
     */
    public function extractDomain(string $input): ?string
    {
        $input = trim($input);
        
        // Add http:// if no protocol specified
        if (!preg_match('~^https?://~i', $input)) {
            $input = 'http://' . $input;
        }
        
        // Parse URL
        $parsed = parse_url($input);
        
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }
        
        return $parsed['host'];
    }

    /**
     * Normalize domain (remove www, convert to lowercase)
     */
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower($domain);
        $domain = preg_replace('/^www\./', '', $domain);
        return $domain;
    }

    /**
     * Generate URL-safe slug from project name
     */
    public function generateSlug(string $projectName): string
    {
        $slug = strtolower($projectName);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Add random suffix to ensure uniqueness
        $slug .= '-' . substr(md5(uniqid()), 0, 8);
        
        return $slug;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }
        
        return reset($this->errors);
    }
}
