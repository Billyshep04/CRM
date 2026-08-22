<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = ['title_template' => '{site_name}', 'admin_username' => 'webstamp_admin', 'permalink' => '/%postname%/', 'timezone' => 'Europe/London', 'delete_default_content' => true, 'maintenance_defaults' => true];
        $profiles = [
            'starter' => ['name' => 'Web Stamp Starter', 'configuration' => [...$defaults, 'plugins' => []]],
            'standard' => ['name' => 'Web Stamp Standard', 'configuration' => [...$defaults, 'plugins' => ['wordpress-seo']]],
            'woocommerce' => ['name' => 'Web Stamp WooCommerce', 'configuration' => [...$defaults, 'plugins' => ['wordpress-seo', 'woocommerce'], 'woocommerce' => true]],
        ];
        DB::table('wordpress_profiles')->whereIn('slug', ['web-stamp-standard', 'web-stamp-bricks', 'web-stamp-woocommerce'])->update(['active' => false, 'updated_at' => now()]);
        foreach ($profiles as $slug => $profile) {
            $existing = DB::table('wordpress_profiles')->where('slug', $slug)->first()
                ?? DB::table('wordpress_profiles')->where('name', $profile['name'])->first();
            $values = [...$profile, 'slug' => $slug, 'configuration' => json_encode($profile['configuration'], JSON_THROW_ON_ERROR), 'active' => true, 'updated_at' => now()];
            if ($existing) DB::table('wordpress_profiles')->where('id', $existing->id)->update($values);
            else DB::table('wordpress_profiles')->insert([...$values, 'created_at' => now()]);
        }

        DB::table('hosting_packages')->whereNull('shell_access')->orderBy('id')->eachById(function ($package): void {
            $limits = json_decode($package->limits ?: '[]', true) ?: [];
            foreach (['HASSHELL', 'hasshell', 'shell', 'SHELL', 'ssh', 'SSH'] as $key) {
                if (! array_key_exists($key, $limits)) continue;
                $enabled = in_array(strtolower((string) $limits[$key]), ['1', 'true', 'on', 'enabled', 'yes', 'jailshell'], true);
                DB::table('hosting_packages')->where('id', $package->id)->update(['shell_access' => $enabled]);
                break;
            }
        });
    }

    public function down(): void
    {
        DB::table('wordpress_profiles')->whereIn('slug', ['starter', 'standard', 'woocommerce'])->update(['active' => false, 'updated_at' => now()]);
    }
};
