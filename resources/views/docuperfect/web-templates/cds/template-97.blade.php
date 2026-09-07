<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permanently employed natural persons</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><u>Rental Application Process and requirements</u></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><strong>Thank you for downloading the Home Finders rental preapproval form.  </strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><strong>Home Finders Coastal strictly works on preapproval of potential tenants before any viewings will take place.  This process is to ensure that we adhere to the agreement made with owners, and to ensure that you as the tenant only view properties that you are qualified to rent.</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><strong>For the preapproval process you please need to complete the attached rental application form, as well as submit the following documentation with the rental application form to :</strong></span></div>
<div class="corex-h2">Permanently employed natural persons</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Latest payslip</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>3 months bank statements</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>ID</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Proof of residence</strong></span></div>
<div class="corex-h2">Business owners operating out of their personal account</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>6 months bank statements</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>ID</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Proof of residence</strong></span></div>
<div class="corex-h2">Business owners operating out of a business account</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Latest financial statements from accountant / auditor</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>6 months bank statements</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Company registration documents</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Power of attorney for authorized signatory</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>ID of member / director who has Power of attorney</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Proof of company addresss</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">•</span> <span class="corex-clause-text"><strong>Proof of member address</strong></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><strong>Please feel free to email  should you need any assistance, and you are also welcome to visit  to view our latest rental properties.</strong></span></div>
<div class="corex-h2">Thank you</div>
<div class="corex-h2">The Home Finders Coastal Rental Team</div>
<div class="corex-h2">Application for rental</div>
<div class="corex-h2">Personal Details</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Address of property:		<span class="corex-field-value" data-field="property_address">{{ $property_address ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Full name and Surname:	<span class="corex-field-value" data-field="full_name_and_surname">{{ $full_name_and_surname ?? '' }}</span>	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">I.D Number:			<span class="corex-field-value" data-field="id_number">{{ $id_number ?? '' }}</span>Marital Status:<span class="corex-field-value" data-field="marital_status">{{ $marital_status ?? '' }}</span>			</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Spouse Full Name:		<span class="corex-field-value" data-field="spouse_details">{{ $spouse_details ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Spouse I.D Number:		<span class="corex-field-value" data-field="spouse_id">{{ $spouse_id ?? '' }}</span>Citizenship:<span class="corex-field-value" data-field="citizenship">{{ $citizenship ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Current Residential Address:	<span class="corex-field-value" data-field="current_residential_address">{{ $current_residential_address ?? '' }}</span>	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Email Address:			<span class="corex-field-value" data-field="email_address">{{ $email_address ?? '' }}</span>			</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Contact Numbers:	(Cell)	<span class="corex-field-value" data-field="contact_numbers">{{ $contact_numbers ?? '' }}</span>(Work):<span class="corex-field-value" data-field="work_contact_number">{{ $work_contact_number ?? '' }}</span>		</span></div>
<div class="corex-h2">Contact Person not staying with you:</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Name:				<span class="corex-field-value" data-field="person_not_living_with_you_name">{{ $person_not_living_with_you_name ?? '' }}</span>				</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Contact Numbers:	(Cell)	<span class="corex-field-value" data-field="person_not_living_with_you_contact_details">{{ $person_not_living_with_you_contact_details ?? '' }}</span>(Work):<span class="corex-field-value" data-field="person_not_living_with_you_work_contact">{{ $person_not_living_with_you_work_contact ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Current Landlord /Agent / Owner details where you are currently residing:</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Name:				<span class="corex-field-value" data-field="landlord_name">{{ $landlord_name ?? '' }}</span>Tell No:<span class="corex-field-value" data-field="landlord_contact">{{ $landlord_contact ?? '' }}</span>				</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Current Rental Amount:	R<span class="corex-field-value" data-field="current_rental_amount">{{ $current_rental_amount ?? '' }}</span>	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">From:				<span class="corex-field-value" data-field="current_lease_term_from">{{ $current_lease_term_from ?? '' }}</span>To:<span class="corex-field-value" data-field="current_lease_term_to">{{ $current_lease_term_to ?? '' }}</span>				</span></div>
<div class="corex-h1">PLEASE EMAIL THIS APPLICATION FORMS, AS WELL AS ALL SUPPORTING DOCUMENTS TO:</div>
<div class="corex-h2">letting@hfcoastal.co.za</div>
<div class="corex-h2">Employment Details</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Name of Employer:		<span class="corex-field-value" data-field="employer_name">{{ $employer_name ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Position:			<span class="corex-field-value" data-field="position">{{ $position ?? '' }}</span>			</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Employer Address:		<span class="corex-field-value" data-field="employer_address">{{ $employer_address ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Tel No of Employer:		<span class="corex-field-value" data-field="employer_contact_details">{{ $employer_contact_details ?? '' }}</span>Monthly Salary:<span class="corex-field-value" data-field="current_salary">{{ $current_salary ?? '' }}</span>		</span></div>
<div class="corex-h2">Requirement of Lease</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Effective Date of Occupation:	<span class="corex-field-value" data-field="new_lease_start_date">{{ $new_lease_start_date ?? '' }}</span>Rental Terms Required:<span class="corex-field-value" data-field="rental_term">{{ $rental_term ?? '' }}</span>	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Special Conditions:		<span class="corex-field-value" data-field="special_conditions">{{ $special_conditions ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Number of Occupants:	Adults:<span class="corex-field-value" data-field="adults">{{ $adults ?? '' }}</span>Children:<span class="corex-field-value" data-field="children">{{ $children ?? '' }}</span>	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">I hereby declare that all the above information given is true and accurate.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">@include("docuperfect.web-templates.components.signature-line", ['party' => 'tenant'])						</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Signature						Witness</span></div>
<div class="corex-h1">PLEASE EMAIL THIS APPLICATION FORMS, AS WELL AS ALL SUPPORTING DOCUMENTS TO:</div>
<div class="corex-h2">Please Note:</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">1</span> <span class="corex-clause-text">Please submit this application to Home Finders Coastal ASAP via email or by hand delivery, for your application to be processed. Your application will be processed within 1 business day from the time of receipt of this application, 3 months bank statement, 1 months’ pay slip, copy of applicant’s ID’s and proof of residence.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">2</span> <span class="corex-clause-text">If applying through a business, 6 months bank statements of the business, company registration documents, person with signing power ID and proof of residence, and proof of business address. </span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">3</span> <span class="corex-clause-text">No Rental property will be reserved unless the Lease agreement has been signed and returned to Home Finders Coastal either via hand delivery or email, and:</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">4</span> <span class="corex-clause-text">The initial invoice including the deposit and admin has been paid to Home Finders Coastal.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">5</span> <span class="corex-clause-text">For any queries on your application please contact the Rental division on: 039 315 0857 or email: </span></div>
<div class="corex-h2">Tenant Profile Network Consent:</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">The tenant hereby consents that, and authorises the Landlord or agent to, at all times:-</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">a)</span> <span class="corex-clause-text">Contact, request and obtain information from any credit provider (or potential credit provider) or registered credit bureau relevant to an assessment of the behaviour, profile, payment patterns, indebtedness, whereabouts, and creditworthiness of the tenant;</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">b)</span> <span class="corex-clause-text">Furnish information concerning the behaviour, profile, payment patterns, indebtedness, whereabouts, and creditworthiness of the tenant of any registered credit bureau or to any credit provider (or potential credit provider) seeking a trade reference regarding the tenant’s dealings with the landlord.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">I hereby declare that all the above information given is true and accurate.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">@include("docuperfect.web-templates.components.signature-line", ['party' => 'tenant'])						</span></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Agent"], "show_witness" => true])

</div>
</div>

</body>
</html>
