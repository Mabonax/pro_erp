<?php

use App\Domains\TaskManagement\Jobs\SendTaskManagementReminderNotificationsJob;
use App\Domains\TaskManagement\Services\SupportTicketService;
use App\Domains\TaskManagement\Services\WorkTaskService;
use App\Domains\CitizenAccess\Services\CitizenAccessCatalogueService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\StaffDepartmentsSeeder;
use Database\Seeders\SuperAdminUserSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('access-control:resync', function () {
    $this->info('Re-syncing departments, roles, permissions, and super admin user...');

    $this->call('db:seed', [
        '--class' => StaffDepartmentsSeeder::class,
        '--force' => true,
    ]);

    $this->call('db:seed', [
        '--class' => AccessControlSeeder::class,
        '--force' => true,
    ]);

    $this->call('db:seed', [
        '--class' => SuperAdminUserSeeder::class,
        '--force' => true,
    ]);

    $this->info('Access-control re-sync complete.');
})->purpose('Safely re-sync departments, roles, permissions, and the seeded super admin user.');

Artisan::command('citizen-access:seed-catalogue', function () {
    $this->info('Seeding the production-safe Citizen Access catalogue. No operational beneficiary, intake, case, application, or outcome records will be deleted.');

    $counts = app(CitizenAccessCatalogueService::class)->seed();

    foreach ($counts as $key => $value) {
        $this->line("{$key}: {$value}");
    }
})->purpose('Safely create or update the canonical Citizen Access offering catalogue.');

Artisan::command('task-management:send-reminders {--now : Run reminders immediately instead of queueing the job}', function () {
    if ($this->option('now')) {
        $taskCount = app(WorkTaskService::class)->sendOverdueReminders();
        $ticketCount = app(SupportTicketService::class)->sendOverdueReminders();

        $this->info("Task reminders sent: {$taskCount}");
        $this->info("Ticket reminders sent: {$ticketCount}");

        return;
    }

    SendTaskManagementReminderNotificationsJob::dispatch();

    $this->info('Task management reminder job dispatched to the queue.');
})->purpose('Send or queue overdue task and support-ticket reminder notifications.');

Schedule::command('task-management:send-reminders')->hourly();
Schedule::command('staff-attendance:auto-clock-out')->dailyAt(config('staff_attendance.auto_clock_out_time', '17:00'));

if (config('backup.enabled', true)) {
    Schedule::command('system:backup-database --prune')->dailyAt('02:00');
}
