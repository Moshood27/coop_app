<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MemberApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class SecurityEncryptExisting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:encrypt-existing {--chunk=100 : The number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing plain-text PII data in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting encryption of existing PII data...');

        $chunkSize = (int) $this->option('chunk');

        $this->encryptModel(User::class, [
            'phone', 'secondary_phone', 'address', 'residential_address',
            'permanent_address', 'business_address', 'nok_name', 'nok_address',
            'nok_phone', 'guarantor_name', 'guarantor_address', 'guarantor_phone',
            'mosque_address', 'imam_phone', 'spouse_father_name', 'spouse_father_address',
            'spouse_father_business_address', 'spouse_father_phone', 'bvn', 'account_number'
        ], $chunkSize);

        $this->encryptModel(MemberApplication::class, [
            'phone', 'secondary_phone', 'address', 'residential_address',
            'permanent_address', 'business_address', 'nok_name', 'nok_address',
            'nok_phone', 'guarantor_name', 'guarantor_address', 'guarantor_phone',
            'mosque_address', 'imam_phone', 'spouse_father_name', 'spouse_father_address',
            'spouse_father_business_address', 'spouse_father_phone'
        ], $chunkSize);

        $this->info('Encryption process completed!');
    }

    /**
     * Encrypt fields for a given model.
     *
     * @param string $modelClass
     * @param array $fields
     * @param int $chunkSize
     */
    protected function encryptModel($modelClass, array $fields, $chunkSize)
    {
        $this->info("Processing {$modelClass}...");

        $total = $modelClass::count();
        if ($total === 0) {
            $this->info("No records found for {$modelClass}.");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $modelClass::chunk($chunkSize, function ($records) use ($fields, $bar) {
            foreach ($records as $record) {
                $needsSave = false;
                foreach ($fields as $field) {
                    $value = $record->getRawOriginal($field);

                    if ($value === null || $value === '') {
                        continue;
                    }

                    // Check if it's already encrypted
                    try {
                        Crypt::decryptString($value);
                        // If decryption succeeds, it's already encrypted
                    } catch (DecryptException $e) {
                        // If decryption fails, it's plain text, so we encrypt it
                        // By setting the value on the model, the SafeEncrypted cast will handle encryption on save
                        $record->{$field} = $value;
                        $needsSave = true;
                    }
                }

                if ($needsSave) {
                    $record->save();
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->line('');
    }
}
