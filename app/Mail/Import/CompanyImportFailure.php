<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompanyImportFailure extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $settings;

    public $logo;

    public $title;

    public $message;

    public $whitelabel;

    public $user_message;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company, $user_message)
    {
        $this->company = $company;
        $this->user_message = $user_message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->settings = $this->company->settings;
        $this->logo = $this->company->present()->logo();
        $this->title = ctrans('texts.company_import_failure_subject', ['company' => $this->company->present()->name()]);
        $this->whitelabel = $this->company->account->isPaid();

        nlog($this->user_message);
        
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject(ctrans('texts.company_import_failure_subject', ['company' => $this->company->present()->name()]))
                    ->view('email.import.import_failure', ['user_message' => $this->user_message, 'title' => $this->title]);
    }
}