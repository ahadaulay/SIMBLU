<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('barangs');
        Schema::dropIfExists('ruangans');

        if (Schema::hasTable('gedungs')) {
            Schema::table('gedungs', function (Blueprint $table): void {
                if (Schema::hasColumn('gedungs', 'departemen_id')) {
                    $table->dropConstrainedForeignId('departemen_id');
                }

                if (Schema::hasColumn('gedungs', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
            });
        }

        Schema::dropIfExists('kode_barangs');
        Schema::dropIfExists('kategoris');
        Schema::dropIfExists('merek_tipe_barangs');
        Schema::dropIfExists('keadaan_barangs');
        Schema::dropIfExists('penguasaan_barangs');
        Schema::dropIfExists('sumber_barangs');

        if (Schema::hasColumn('users', 'departemen_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('departemen_id'));
        }

        Schema::dropIfExists('departemens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('password_reset_tokens');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
        }
    }

    public function down(): void
    {
        // Modul yang telah dihapus tidak dibuat ulang.
    }
};
