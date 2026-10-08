<?php

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Admin\Models\Admin;

/** The only way to make an admin: there is no sign-up page. Two-factor is set up at first login. */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email} {--name=} {--password=}';

    protected $description = 'Create an admin account for the Nemchiw panel';

    public function handle(): int
    {
        $password = $this->option('password') ?? $this->secret('Password (at least 12 characters)');
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name') ?? 'Admin', 'password' => $password];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:admins,email'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        Admin::query()->create($data);
        $this->info("Admin {$data['email']} created. Set up two-factor at first login.");

        return self::SUCCESS;
    }
}
