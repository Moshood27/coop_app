$user = \App\Models\User::updateOrCreate(
    ['membership_number' => 'TEST001'],
    [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]
);

echo "User ID: " . $user->id . "\n";

$import = new \App\Imports\LoansImport(now());

$row = [
    'membership_no' => 'TEST001',
    'original_loan_amount' => '100000',
    'remaining_principal' => '40000',
    'next_installment_amount' => '10000',
    'total_repaid_to_date' => '60000',
    'interval' => 'monthly',
    'total_installments' => '10',
    'received_at' => '2026-01-01',
    'defaulted_at' => '',
];

\Illuminate\Support\Facades\DB::beginTransaction();

try {
    $model = $import->model($row);
    if ($model) {
        $model->save();
        echo "Loan saved. ID: " . $model->id . "\n";
        
        $savedLoan = \App\Models\QardHasan::find($model->id);
        if ($savedLoan) {
            echo "Verified: Loan exists in DB. Status: " . $savedLoan->status . "\n";
        } else {
            echo "Error: Loan NOT found in DB after save!\n";
        }
    } else {
        echo "Model was NULL\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    \Illuminate\Support\Facades\DB::rollBack();
}
