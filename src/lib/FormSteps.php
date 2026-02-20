<?php
declare(strict_types=1);

class FormSteps
{
    private static array $steps = [
        1 => [
            'title'       => 'Tell us about your project',
            'description' => 'Select the primary service type that best describes what you need.',
            'fields'      => [
                [
                    'name'     => 'service_type',
                    'label'    => 'Service Type',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        'web_design'      => 'Web Design',
                        'web_development' => 'Web Development',
                        'ecommerce'       => 'E-Commerce',
                        'software'        => 'Custom Software',
                        'ai_web_app'      => 'AI-Driven Web Application',
                        'ai_native_app'   => 'AI-Driven Native Application',
                    ],
                ],
                [
                    'name'     => 'project_name',
                    'label'    => 'Project Name or Brief Description',
                    'type'     => 'text',
                    'required' => true,
                    'max'      => 120,
                ],
            ],
        ],
        2 => [
            'title'       => 'Project Complexity',
            'description' => 'How would you describe the scope and complexity of your project?',
            'fields'      => [
                [
                    'name'     => 'complexity',
                    'label'    => 'Complexity Level',
                    'type'     => 'radio',
                    'required' => true,
                    'options'  => [
                        'simple'   => 'Simple - basic pages, minimal interactions',
                        'moderate' => 'Moderate - custom features, some integrations',
                        'complex'  => 'Complex - advanced logic, multiple integrations',
                        'custom'   => 'Custom - AI, automation, or enterprise-scale work',
                    ],
                ],
            ],
        ],
        3 => [
            'title'       => 'Add-On Services',
            'description' => 'Select any additional services you would like included in your estimate.',
            'fields'      => [
                [
                    'name'     => 'addons',
                    'label'    => 'Additional Services',
                    'type'     => 'checkbox_group',
                    'required' => false,
                    'options'  => [
                        'seo_basic'       => 'SEO Setup - Basic',
                        'seo_advanced'    => 'SEO Setup - Advanced',
                        'copywriting'     => 'Copywriting',
                        'branding'        => 'Branding and Identity',
                        'maintenance'     => 'Ongoing Maintenance',
                        'hosting_setup'   => 'Hosting Configuration',
                        'api_integration' => 'Third-Party API Integration',
                        'automation'      => 'Business Process Automation',
                    ],
                ],
            ],
        ],
        4 => [
            'title'       => 'Your Contact Information',
            'description' => 'Provide your name and email address so we can follow up with your formal quote.',
            'fields'      => [
                [
                    'name'     => 'contact_name',
                    'label'    => 'Full Name',
                    'type'     => 'text',
                    'required' => true,
                    'max'      => 80,
                ],
                [
                    'name'     => 'contact_email',
                    'label'    => 'Email Address',
                    'type'     => 'email',
                    'required' => true,
                    'max'      => 120,
                ],
                [
                    'name'     => 'contact_company',
                    'label'    => 'Company or Organization (optional)',
                    'type'     => 'text',
                    'required' => false,
                    'max'      => 100,
                ],
                [
                    'name'     => 'timeline',
                    'label'    => 'Desired Timeline',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        'asap'       => 'As soon as possible',
                        '1_month'    => 'Within 1 month',
                        '3_months'   => 'Within 3 months',
                        '6_months'   => 'Within 6 months',
                        'flexible'   => 'Flexible',
                    ],
                ],
            ],
        ],
    ];

    public static function count(): int
    {
        return count(self::$steps);
    }

    public static function getStep(int $step): array
    {
        return self::$steps[$step] ?? [];
    }

    public static function all(): array
    {
        return self::$steps;
    }

    public static function validate(int $step, array $post): array
    {
        $errors    = [];
        $stepData  = self::getStep($step);

        if (empty($stepData)) {
            return $errors;
        }

        foreach ($stepData['fields'] as $field) {
            $name  = $field['name'];
            $value = $post[$name] ?? '';

            if ($field['required'] && empty($value) && $value !== '0') {
                $errors[$name] = $field['label'] . ' is required.';
                continue;
            }

            if ($field['type'] === 'email' && !empty($value)) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Please enter a valid email address.';
                }
            }

            if (isset($field['max']) && strlen($value) > $field['max']) {
                $errors[$name] = $field['label'] . ' must not exceed ' . $field['max'] . ' characters.';
            }
        }

        return $errors;
    }
}
