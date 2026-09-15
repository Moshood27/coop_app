<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$app->make('db')->beginTransaction();

try {
    // 1. Create a dummy admin
    $admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin_test@example.com',
    ]);

    // 2. Create a dummy member
    $member = User::factory()->create([
        'surname' => 'OldSurname',
        'name' => 'OldName',
        'membership_number' => 'OLD-123',
        'email' => 'member_test@example.com',
        'phone' => '1234567890',
        'gender' => 'male',
        'branch_id' => 1, // Assuming branch 1 exists
    ]);

    echo "Initial Member ID: " . $member->id . "\n";
    echo "Initial Membership Number: " . $member->membership_number . "\n";

    // 3. Mock the request to update the member
    $updateData = [
        'surname' => 'NewSurname',
        'name' => 'NewName',
        'membership_number' => 'NEW-456',
        'email' => 'member_test_new@example.com',
        'phone' => '0987654321',
        'gender' => 'female',
        'branch_id' => 1,
        'address' => 'New Address',
    ];

    $controller = $app->make(\App\Http\Controllers\Api\AdminMemberController::class);
    
    // Simulate the admin user being authenticated
    $request = Request::create('/api/admin/members/' . $member->id, 'PATCH', $updateData);
    $request->setUserResolver(fn() => $admin);

    $response = $controller->update($request, $member);

    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response content: " . $response->getContent() . "\n";

    $member->refresh();
    echo "Updated Membership Number: " . $member->membership_number . "\n";
    echo "Updated Surname: " . $member->surname . "\n";

    if ($member->membership_number === 'NEW-456' && $member->surname === 'NewSurname') {
        echo "SUCCESS: Member profile updated successfully!\n";
    } else {
        echo "FAILURE: Member profile update failed.\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    $app->make('db')->rollBack();
}
