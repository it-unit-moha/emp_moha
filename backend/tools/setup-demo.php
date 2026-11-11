<?php

// Local-only demo setup. Never reads or writes the imported MySQL database.
use App\Enums\Role;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Demo setup requires APP_ENV=local.');
}
$path = database_path('demo.sqlite');
if (! file_exists($path)) {
    touch($path);
}
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
DB::purge('sqlite');
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
foreach (['division' => 'division', 'unit' => 'unit_name', 'designation' => 'designation'] as $table => $column) {
    if (! Schema::hasTable($table)) {
        Schema::create($table, function (Blueprint $t) use ($column, $table) {
            $t->increments('id');
            $t->string($column)->nullable();
            if ($table === 'division') {
                $t->integer('status')->default(1);
            }
        });
    }
}
if (! Schema::hasTable('employee_data')) {
    Schema::create('employee_data', function (Blueprint $t) {
        $t->increments('id');
        foreach (['name', 'nic', 'emp_no', 'email', 'address', 'contact_no', 'gender', 'unit', 'qr_code', 'application_form', 'image', 'signature'] as $column) {
            $t->string($column)->nullable();
        }
        $t->unique('nic');
        foreach (['dob', 'date_issued', 'date_expiry'] as $column) {
            $t->date($column)->nullable();
        }
        foreach (['division', 'designation', 'is_active', 'is_printed'] as $column) {
            $t->integer($column)->nullable();
        }
        $t->timestamp('inserted_on')->useCurrent();
        $t->dateTime('updated_on')->nullable();
    });
}
DB::transaction(function () {
    foreach (['division' => ['division', ['Demo Administration', 'Demo Human Resources', 'Demo Finance']], 'unit' => ['unit_name', ['Demo Registry', 'Demo Personnel', 'Demo Accounts']], 'designation' => ['designation', ['Demo Officer', 'Demo Assistant', 'Demo Manager']]] as $table => [$column, $names]) {
        foreach ($names as $name) {
            if (! DB::table($table)->where($column, $name)->exists()) {
                DB::table($table)->insert([$column => $name]);
            }
        }
    }
    $divisions = DB::table('division')->orderBy('id')->pluck('id');
    $units = DB::table('unit')->orderBy('id')->pluck('id');
    $designations = DB::table('designation')->orderBy('id')->pluck('id');
    foreach (['Alex Silva', 'Sam Perera', 'Taylor Fernando', 'Jordan De Silva', 'Casey Jayasuriya', 'Robin Senanayake', 'Jamie Dias', 'Morgan Peiris', 'Avery Wijesinghe', 'Riley Costa', 'Cameron Rodrigo', 'Drew Mendis'] as $i => $name) {
        $number = $i + 1;
        $nic = sprintf('DEMO%08d', $number);
        if (! DB::table('employee_data')->where('nic', $nic)->exists()) {
            DB::table('employee_data')->insert([
                'name' => 'Demo '.$name, 'nic' => $nic, 'emp_no' => sprintf('DEMO-%03d', $number),
                'email' => 'employee'.$number.'@example.test', 'address' => 'Synthetic demo address '.$number,
                'dob' => '1990-01-15', 'division' => $divisions[$i % 3], 'unit' => (string) $units[$i % 3],
                'designation' => $designations[$i % 3], 'is_active' => $i < 10 ? 1 : 0,
            ]);
        }
    }
});
foreach (DB::table('employee_data')->where('emp_no', 'like', 'DEMO-%')->orderBy('id')->get() as $i => $employee) {
    EmployeeProfile::firstOrCreate(['employee_id' => $employee->id], ['service_grade' => 'Demo Grade III', 'enroll_id' => sprintf('DEMO%03d', $i + 1), 'staff_category' => $i % 2 ? 'pl' : 'other', 'vacation_days' => 24]);
    app(WorkflowService::class)->initialize($employee->id, (int) now('Asia/Colombo')->year);
}
$email = 'demo.admin@example.test';
if (! User::where('email', $email)->exists()) {
    $password = 'Demo!'.bin2hex(random_bytes(8));
    User::create(['name' => 'Demo Administrator', 'email' => $email, 'password' => $password, 'role' => Role::SuperAdmin]);
    echo "Demo email: $email\nDemo password: $password\n";
} else {
    echo "Demo account already exists; its password was preserved.\n";
}
$envPath = base_path('.env');
$env = file_get_contents($envPath);
if (! file_exists(base_path('.env.before-demo'))) {
    file_put_contents(base_path('.env.before-demo'), $env);
}
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => '"'.str_replace('\\', '/', $path).'"'] as $key => $value) {
    $env = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $env, -1, $count);
    if (! $count) {
        $env .= "\n$key=$value\n";
    }
}
file_put_contents($envPath, $env);
Artisan::call('config:clear');
echo "Local application configured for isolated demo.sqlite: 12 synthetic employees, 3 divisions, 3 units, 3 designations.\n";
