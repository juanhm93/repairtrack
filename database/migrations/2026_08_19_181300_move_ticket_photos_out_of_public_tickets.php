<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Move uploaded ticket photos out of public/tickets so that directory
     * no longer shadows the /tickets Laravel routes (403 on Apache/LiteSpeed).
     */
    public function up(): void
    {
        if (! app()->runningUnitTests()) {
            $this->movePhotoDirectory(public_path('tickets'), public_path('uploads/tickets'));
        }

        foreach (DB::table('ticket_photos')->where('path', 'like', 'tickets/%')->get() as $photo) {
            if (! is_string($photo->path) || str_starts_with($photo->path, 'uploads/')) {
                continue;
            }

            DB::table('ticket_photos')->where('id', $photo->id)->update([
                'path' => 'uploads/'.$photo->path,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('ticket_photos')->where('path', 'like', 'uploads/tickets/%')->get() as $photo) {
            if (! is_string($photo->path)) {
                continue;
            }

            DB::table('ticket_photos')->where('id', $photo->id)->update([
                'path' => substr($photo->path, strlen('uploads/')),
            ]);
        }

        if (! app()->runningUnitTests()) {
            $this->movePhotoDirectory(public_path('uploads/tickets'), public_path('tickets'));
        }
    }

    private function movePhotoDirectory(string $from, string $to): void
    {
        if (! File::isDirectory($from)) {
            return;
        }

        File::ensureDirectoryExists(dirname($to));

        if (! File::isDirectory($to)) {
            File::moveDirectory($from, $to);

            return;
        }

        File::copyDirectory($from, $to);
        File::deleteDirectory($from);
    }
};
