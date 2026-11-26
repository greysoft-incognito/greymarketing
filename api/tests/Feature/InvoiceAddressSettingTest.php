<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceAddressSettingTest extends TestCase
{
    public function testTheMigrationAddsTheSettingWithoutOverwritingASavedAddress(): void
    {
        $migration = require database_path('migrations/2026_11_30_000002_add_invoice_address_setting.php');

        DB::table('configurations')->where('key', 'invoice_address')->delete();
        $migration->up();

        $this->assertSame('', DB::table('configurations')->where('key', 'invoice_address')->value('value'));

        DB::table('configurations')->where('key', 'invoice_address')->update(['value' => '12 Allen Avenue']);
        $migration->up();

        $this->assertSame('12 Allen Avenue', DB::table('configurations')->where('key', 'invoice_address')->value('value'));
        $this->assertSame(1, DB::table('configurations')->where('key', 'invoice_address')->count());
    }
}
