<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FeeBillIdentity;
use App\Models\FeeHead;
use App\Models\FeeReceipt;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;

$user = User::factory()->create();
UserRole::create(['user_id'=>$user->id,'role'=>'accountant']);
$student = Student::create(['first_name'=>'Test','last_name'=>'Student','gender'=>'male','dob'=>'2014-01-01','class_applied'=>'Grade 6','adm_number'=>'ADM-TEST-9999','status'=>'active']);
$head = FeeHead::create(['name'=>'TestFee2','frequency'=>'monthly','is_active'=>true]);
$receipt = FeeReceipt::create(['receipt_no'=>'RCP-2026-00008888','student_id'=>$student->id,'academic_year'=>'2026-2027','receipt_date'=>now()->toDateString(),'subtotal'=>500,'discount'=>0,'fine'=>0,'total_due'=>500,'paid_amount'=>0,'balance'=>500,'status'=>'unpaid']);
$item = $receipt->items()->create(['fee_head_id'=>$head->id,'description'=>'TestFee2','period'=>'Baisakh','amount'=>500]);
$receipt->claimIdentities(collect([$item]));
echo "before:".FeeBillIdentity::count().PHP_EOL;

// simulate controller
$controller = new App\Http\Controllers\Admin\FeeController();
\Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create('/','DELETE');
$response = $controller->destroy($receipt);
echo "after:".FeeBillIdentity::count().PHP_EOL;
var_dump($response->getSession()->get('success'));
var_dump($response->getSession()->get('errors'));
