<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebTemplateController extends Controller
{
    /**
     * Preview the Letting Mandate V5 web template with sample data.
     */
    public function lettingMandateV5(Request $request)
    {
        $data = [
            'lessor_name'        => 'Shaun Hartley',
            'agent_name'         => 'Maggie Venter',
            'property_address'   => '14 Marine Drive, Uvongo',
            'rental_amount'      => '8 500',
            'rental_in_words'    => 'Eight thousand five hundred Rand',
            'mandate_day'        => '7th',
            'mandate_month'      => 'March',
            'mandate_year'       => '26',
            'commission_percent' => '7.5',
            'account_holder'     => 'S Hartley',
            'bank_name'          => 'FNB',
            'account_number'     => '62845930127',
            'branch_name'        => 'Shelly Beach (220629)',
            'owner_contact'      => '082 123 4567',
            'owner_email'        => 'shaun@email.com',
            'signed_at_location' => 'Shelly Beach',
            'signed_day'         => '7th',
            'signed_month'       => 'March',
            'signed_time'        => '10:00',
            'signed_ampm'        => 'am',
        ];

        // Allow ?empty=1 to preview blank (unfilled) version
        if ($request->boolean('empty')) {
            $data = [];
        }

        return view('docuperfect.web-templates.letting-mandate-v5', $data);
    }
}
