<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AgentMember;
use Illuminate\Support\Facades\Hash;

class AgentMemberSeeder extends Seeder
{
    public function run(): void
    {
        AgentMember::create([
            'member_code' => 'AGT-0001',
            'name'        => 'Administrator',
            'email'       => 'admin@example.com',
            'phone'       => '0800000000',
            'password'    => Hash::make('123456'),
            'status'      => 'active',
            'address'     => '123 Main Street',
            'province'    => 'Bangkok',
            'zipcode'     => '10100',
        ]);

        $this->command->info('Admin account created: admin@example.com / 123456');
    }
}
