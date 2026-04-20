<?php

namespace KP\AgentReady\Modules;

/**
 * Appends Content Signals directives to robots.txt.
 *
 * @see https://contentsignals.org/
 */
class RobotsTxt extends AbstractModule
{

    public function register(): void
    {
        add_filter('robots_txt', [$this, 'append']);
    }

    public function append(string $output): string
    {
        if (! $this->opt('content_signals_enabled', true)) {
            return $output;
        }

        $ai_train = $this->opt('cs_ai_train', 'no');
        $search   = $this->opt('cs_search',   'yes');
        $ai_input = $this->opt('cs_ai_input', 'no');

        $output .= "\n# Content Signals (https://contentsignals.org/)\n";
        $output .= "Content-Signal: ai-train={$ai_train}, search={$search}, ai-input={$ai_input}\n";

        return $output;
    }
}
