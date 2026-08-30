<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-lifecycle (handbook Ch. 28.7): core registries archive instead of
 * destroying. Natural-key unique indexes are rebuilt to include
 * `deleted_at` so a key can be reused once its previous holder is
 * archived (active rows keep NULL → distinct in both MySQL and SQLite).
 */
return new class extends Migration
{
    /** @var list<string> tables without a natural unique key */
    private const PLAIN = [
        'guardians',
        'calendar_events',
        'finance_fee_structures',
        'comm_announcements',
        'comm_broadcasts',
        'comm_message_threads',
    ];

    /** @var array<string, list<string>> tables with a natural unique key */
    private const UNIQUE = [
        'students' => ['tenant_id', 'admission_number'],
        'staff_members' => ['tenant_id', 'staff_number'],
        'campuses' => ['tenant_id', 'code'],
        'academic_years' => ['tenant_id', 'label'],
        'terms' => ['academic_year_id', 'sequence'],
        'applications' => ['tenant_id', 'reference'],
        'subjects' => ['tenant_id', 'code'],
    ];

    /** @var array<string, array{columns: list<string>, index: string}> */
    private const NAMED_UNIQUE = [
        'finance_invoices' => ['columns' => ['tenant_id', 'number'], 'index' => 'finance_invoices_number_unique'],
        'course_sections' => ['columns' => ['tenant_id', 'academic_year_id', 'subject_id', 'section_label'], 'index' => 'course_sections_year_subject_section_unique'],
    ];

    public function up(): void
    {
        foreach (array_merge(self::PLAIN, array_keys(self::UNIQUE), array_keys(self::NAMED_UNIQUE)) as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        foreach (self::UNIQUE as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                // MySQL refuses to drop a unique index that backs a
                // foreign key; give the FK an alternative index first.
                $blueprint->index($columns[0], "{$table}_fk_support_idx");
                $blueprint->dropUnique($columns);
                $blueprint->unique([...$columns, 'deleted_at']);
            });
        }

        foreach (self::NAMED_UNIQUE as $table => $config) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $config): void {
                $blueprint->index($config['columns'][0], "{$table}_fk_support_idx");
                $blueprint->dropUnique($config['index']);
                $blueprint->unique([...$config['columns'], 'deleted_at'], $config['index']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::NAMED_UNIQUE as $table => $config) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $config): void {
                $blueprint->index($config['columns'][0], "{$table}_fk_support_idx");
                $blueprint->dropUnique($config['index']);
                $blueprint->unique($config['columns'], $config['index']);
            });
        }

        foreach (self::UNIQUE as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                $blueprint->index($columns[0], "{$table}_fk_support_idx");
                $blueprint->dropUnique([...$columns, 'deleted_at']);
                $blueprint->unique($columns);
            });
        }

        foreach (array_merge(self::PLAIN, array_keys(self::UNIQUE), array_keys(self::NAMED_UNIQUE)) as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
