<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Application\Job\JobWorker;

final readonly class WordPressJobWorkerHooks
{
    public const HOOK = 'eventflow_worker_tick';
    public const SCHEDULE = 'eventflow_every_minute';

    public function __construct(private JobWorker $worker, private int $maximumJobsPerTick = 10) {}

    public function register(): void
    {
        if (!function_exists('add_action') || !function_exists('add_filter')) return;
        add_filter('cron_schedules', $this->addSchedule(...));
        add_action('init', $this->ensureScheduled(...));
        add_action(self::HOOK, $this->run(...));
        if (defined('EVENTFLOW_PLUGIN_FILE') && function_exists('register_deactivation_hook')) {
            register_deactivation_hook((string) EVENTFLOW_PLUGIN_FILE, self::deactivate(...));
        }
    }

    public function addSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE] = ['interval' => 60, 'display' => 'EventFlow every minute'];
        return $schedules;
    }

    public function ensureScheduled(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) return;
        if (wp_next_scheduled(self::HOOK) === false) wp_schedule_event(time() + 60, self::SCHEDULE, self::HOOK);
    }

    public function run(): void
    {
        $workerId = 'wordpress-' . substr(hash('sha256', function_exists('site_url') ? (string) site_url() : 'eventflow'), 0, 24);
        for ($processed = 0; $processed < $this->maximumJobsPerTick; $processed++) {
            if (!$this->worker->runOne($workerId)) return;
        }
    }

    public static function deactivate(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event')) return;
        while (($timestamp = wp_next_scheduled(self::HOOK)) !== false) wp_unschedule_event($timestamp, self::HOOK);
    }
}
