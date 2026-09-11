<?php

namespace App\Mail;

use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationGuarantorReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $guarantor;
    public $testimony;

    /**
     * Create a new message instance.
     */
    public function __construct(MemberApplication $application, User $guarantor)
    {
        $this->application = $application;
        $this->guarantor = $guarantor;
        $this->testimony = "I hereby testify before Allah (SWT) that the applicant, {$application->full_name}, is known to me to be a person of good character and Islamic integrity. I vouch for their trustworthiness and believe them to be capable of fulfilling their obligations to the Cooperative. I understand that by acting as a guarantor, I am affirming my belief in their honesty and reliability in accordance with Islamic principles of mutual support and trust.";
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->markdown('emails.member.registration_guarantor_reminder')
            ->subject('Action Required: Guarantor Request for New Member Registration')
            ->with([
                'applicantName' => $this->application->full_name,
                'guarantorName' => $this->guarantor->full_name,
                'testimony' => $this->testimony,
            ]);
    }
}
