<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report;

use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;

class ClientBalanceReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax
    public Writer $csv;

    public string $date_key = 'created_at';

    private string $template = '/views/templates/reports/client_balance_report.html';

    private array $clients = [];

    public array $report_keys = [
        'client_name',
        'client_number',
        'id_number',
        'invoices',
        'invoice_balance',
        'credit_balance',
        'payment_balance',
    ];

    /**
        @param array $input
        [
            'date_range',
            'start_date',
            'end_date',
            'clients',
            'client_id',
        ]
    */
    public function __construct(public Company $company, public array $input)
    {
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.client_balance_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->orderBy('balance', 'desc')
            ->cursor()
            ->each(function ($client) {

                $this->csv->insertOne($this->buildRow($client));

            });

        return $this->csv->toString();

    }

    public function buildHeader(): array
    {
        $headers = [];

        foreach ($this->report_keys as $key) {
            $headers[] = ctrans("texts.{$key}");
        }

        return $headers;

    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $data = [
            'clients' => $this->clients,
            'company_logo' => $this->company->present()->logo(),
            'company_name' => $this->company->present()->name(),
            'created_on' => $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale()),
            'created_by' => $user_name,
        ];

        $ts = new TemplateService();

        $ts_instance = $ts->setCompany($this->company)
                    ->setData($data)
                    ->setRawTemplate(file_get_contents(resource_path($this->template)))
                    ->parseNinjaBlocks()
                    ->save();

        return $ts_instance->getPdf();
    }

    private function buildRow(Client $client): array
    {
        $query = Invoice::query()->where('client_id', $client->id)
                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]);

        $query = $this->addDateRange($query, 'invoices');

        $item = [
            $client->present()->name(),
            $client->number,
            $client->id_number,
            $query->count(),
            $query->sum('balance'),
            Number::formatMoney($client->credit_balance, $this->company),
            Number::formatMoney($client->payment_balance, $this->company),
        ];

        $this->clients[] = $item;

        return $item;
    }
}