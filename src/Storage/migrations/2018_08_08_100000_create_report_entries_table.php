<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportEntriesTable extends Migration
{
    /**
     * The database schema.
     *
     * @var \Illuminate\Database\Schema\Builder
     */
    protected $schema;

    /**
     * Create a new migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->schema = Schema::connection($this->getConnection());
    }

    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function getConnection()
    {
        return config('nitm-reporting.storage.database.connection');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->schema->create('nitm_reporting_entries', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->uuid('uuid');
            $table->uuid('batch_id');
            $table->string('family_hash')->nullable();
            $table->boolean('should_display_on_index')->default(true);
            $table->string('type', 20);
            $table->bigInteger('entity_id')->nullable();
            $table->text('entity_type')->nullable();
            $table->text('params')->default('{}');
            $table->longText('content')->default('{}');
            $table->dateTime('created_at')->nullable();

            $table->unique('uuid');
            $table->index('batch_id');
            $table->index('family_hash');
            $table->index('created_at');
            $table->index(['type', 'should_display_on_index']);
        });

        $this->schema->create('nitm_reporting_entries_exports', function (Blueprint $table) {
            $table->uuid('entry_uuid');
            $table->text('sent_to')->nullable();
            $table->text('url')->nullable();

            $table->index(['entry_uuid']);

            $table->foreign('entry_uuid')
                ->references('uuid')
                ->on('nitm_reporting_entries')
                ->onDelete('cascade');
        });

        $this->schema->create('nitm_reporting_entries_tags', function (Blueprint $table) {
            $table->uuid('entry_uuid');
            $table->string('tag');

            $table->index(['entry_uuid', 'tag']);
            $table->index('tag');

            $table->foreign('entry_uuid')
                ->references('uuid')
                ->on('nitm_reporting_entries')
                ->onDelete('cascade');
        });

        $this->schema->create('nitm_reporting_monitoring', function (Blueprint $table) {
            $table->string('tag');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->schema->dropIfExists('nitm_reporting_entries_tags');
        $this->schema->dropIfExists('nitm_reporting_entries_exports');
        $this->schema->dropIfExists('nitm_reporting_entries');
        $this->schema->dropIfExists('nitm_reporting_monitoring');
    }
}