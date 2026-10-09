<?php

declare(strict_types=1);

const APP_NAME = 'E-Barangay Management System';
const APP_BARANGAY = 'Barangay Bigaan';   // change to your barangay name
const APP_MUNICIPALITY = 'Calauag';       // used on certificates
const APP_PROVINCE = 'Quezon';            // used on certificates
const APP_DEMO = true;                    // shows demo-account shortcuts on the login page; set false for real use

function app_modules(): array
{
    static $modules = null;

    if ($modules !== null) {
        return $modules;
    }

    $modules = [
        'residents' => [
            'label' => 'Resident Profiles',
            'group' => 'Core',
            'description' => 'Resident registration, verification, classifications and profile history.',
            'type' => 'custom',
            'url' => 'modules/residents.php',
            'prefix' => 'RES',
        ],
        'households' => [
            'label' => 'Households',
            'group' => 'Core',
            'description' => 'Household heads, family members, addresses, income and utility information.',
            'type' => 'custom',
            'url' => 'modules/households.php',
            'prefix' => 'HH',
        ],
        'puroks' => [
            'label' => 'Purok / Sitio',
            'group' => 'Core',
            'description' => 'Purok leaders, assigned areas and population summaries.',
            'type' => 'custom',
            'url' => 'modules/puroks.php',
            'prefix' => 'PRK',
        ],
        'documents' => [
            'label' => 'Certification Requests',
            'group' => 'Transactions',
            'description' => 'Clearances, certificates, requirements, payment, approval, release and QR verification.',
            'type' => 'records',
            'prefix' => 'DOC',
        ],
        'permits' => [
            'label' => 'Permits & Clearances',
            'group' => 'Transactions',
            'description' => 'Business clearances, permit applications, assessment, approval and release.',
            'type' => 'records',
            'prefix' => 'PER',
        ],
        'services' => [
            'label' => 'Barangay Services',
            'group' => 'Transactions',
            'description' => 'Assistance, facility-related requests, programs and service tracking.',
            'type' => 'records',
            'prefix' => 'SRV',
        ],
        'complaints' => [
            'label' => 'Complaints & Blotter',
            'group' => 'Cases',
            'description' => 'Complaint filing, incident logs, evidence, witnesses, referral and resolution.',
            'type' => 'records',
            'prefix' => 'CB',
        ],
        'katarungang' => [
            'label' => 'Katarungang Pambarangay',
            'group' => 'Cases',
            'description' => 'Case filing, summons, hearings, mediation, settlement and CFA history.',
            'type' => 'records',
            'prefix' => 'KP',
        ],
        'peace_order' => [
            'label' => 'Peace & Order',
            'group' => 'Cases',
            'description' => 'Tanod duty schedules, patrol logs, incidents and peace and order reports.',
            'type' => 'records',
            'prefix' => 'PO',
        ],
        'protection' => [
            'label' => 'BCPC / VAWC Protection',
            'group' => 'Cases',
            'description' => 'Restricted child protection and VAWC case monitoring workflows.',
            'type' => 'records',
            'prefix' => 'PRT',
            'sensitive' => true,
        ],
        'officials' => [
            'label' => 'Officials & Personnel',
            'group' => 'Governance',
            'description' => 'Barangay officials, personnel, terms, signatures and responsibilities.',
            'type' => 'records',
            'prefix' => 'OFC',
        ],
        'budget' => [
            'label' => 'Budget & Disbursement',
            'group' => 'Finance',
            'description' => 'Budget allocation, expenses, vouchers, utilization and financial reporting.',
            'type' => 'records',
            'prefix' => 'BDG',
        ],
        'treasury' => [
            'label' => 'Payment Transactions',
            'group' => 'Finance',
            'description' => 'Fees, payments, collections, official receipts and collection reports.',
            'type' => 'custom',
            'url' => 'modules/payments.php',
            'prefix' => 'PAY',
        ],
        'communication' => [
            'label' => 'Communication Center',
            'group' => 'Communication',
            'description' => 'Email, SMS, in-app notifications, recipient groups, templates and delivery history.',
            'type' => 'records',
            'prefix' => 'MSG',
        ],
        'feed' => [
            'label' => 'Community Feed',
            'group' => 'Communication',
            'description' => 'Barangay news posts with photos, visible to every resident on the dashboard.',
            'type' => 'custom',
            'url' => 'modules/feed.php',
            'prefix' => 'POST',
        ],
        'announcements' => [
            'label' => 'Announcements',
            'group' => 'Communication',
            'description' => 'Public notices, audience targeting, publishing, scheduling and history.',
            'type' => 'records',
            'prefix' => 'ANN',
        ],
        'events' => [
            'label' => 'Events & Activities',
            'group' => 'Community',
            'description' => 'Community calendar: schedule, venue and organizer of barangay events and activities.',
            'type' => 'custom',
            'url' => 'modules/events.php',
            'prefix' => 'EVT',
        ],
        'assembly_meetings' => [
            'label' => 'Assembly & Meetings',
            'group' => 'Governance',
            'description' => 'Schedules, agenda, attendance, minutes, action items and resolutions.',
            'type' => 'records',
            'prefix' => 'MTG',
        ],
        'official_records' => [
            'label' => 'Ordinances & Resolutions',
            'group' => 'Governance',
            'description' => 'Ordinances, resolutions, memoranda, attachments, search and archive.',
            'type' => 'records',
            'prefix' => 'ORD',
        ],
        'projects' => [
            'label' => 'Barangay Development & Projects',
            'group' => 'Community',
            'description' => 'Development plans, proposals, timelines, progress and accomplishment reports.',
            'type' => 'records',
            'prefix' => 'PRJ',
        ],
        'facilities' => [
            'label' => 'Facilities & Reservations',
            'group' => 'Operations',
            'description' => 'Facility list, time-slot reservations, approval and double-booking prevention.',
            'type' => 'custom',
            'url' => 'modules/facilities.php',
            'prefix' => 'FAC',
        ],
        'assets' => [
            'label' => 'Assets & Inventory',
            'group' => 'Operations',
            'description' => 'Properties, equipment, quantity, condition, location, assignment and maintenance.',
            'type' => 'records',
            'prefix' => 'AST',
        ],
        'environment' => [
            'label' => 'Environment & Sanitation',
            'group' => 'Operations',
            'description' => 'Waste schedules, clean-up activities, sanitation concerns and monitoring.',
            'type' => 'records',
            'prefix' => 'ENV',
        ],
        'assistance' => [
            'label' => 'Community Assistance',
            'group' => 'Community',
            'description' => 'Assistance programs, eligibility, beneficiaries, distribution and history.',
            'type' => 'records',
            'prefix' => 'ASTN',
        ],
        'reports' => [
            'label' => 'Reports & Analytics',
            'group' => 'Control',
            'description' => 'Population, requests, cases, collections, projects and printable exports.',
            'type' => 'custom',
            'url' => 'modules/reports.php',
            'prefix' => 'RPT',
        ],
        'search' => [
            'label' => 'Global Search',
            'group' => 'Control',
            'description' => 'Permission-aware search across residents, households, requests, payments and cases.',
            'type' => 'custom',
            'url' => 'modules/search.php',
            'prefix' => 'SRC',
        ],
        'audit' => [
            'label' => 'Audit Trail',
            'group' => 'Control',
            'description' => 'Who, what, when and affected record for official system actions.',
            'type' => 'custom',
            'url' => 'modules/audit.php',
            'prefix' => 'AUD',
        ],
        'settings' => [
            'label' => 'User Accounts',
            'group' => 'Control',
            'description' => 'Users, roles, permissions, backup reminders and system settings.',
            'type' => 'custom',
            'url' => 'modules/settings.php',
            'prefix' => 'SET',
        ],
    ];

    return $modules;
}

function future_modules(): array
{
    return [
        'sk' => 'SK Management',
        'health' => 'Barangay Health Center',
        'emergency' => 'Emergency & Disaster Response',
        'agriculture' => 'Agriculture',
        'senior_pwd' => 'Dedicated Senior Citizen / PWD Management',
    ];
}

function workflow_statuses(): array
{
    return [
        'Pending',
        'Approved',
        'Under Review',
        'For Payment',
        'For Approval',
        'Processing',
        'Ready for Release',
        'Completed',
        'Rejected',
        'Returned for Correction',
        'Cancelled',
        'Archived',
    ];
}

function resident_statuses(): array
{
    return [
        'Pending Verification',
        'Active',
        'Inactive',
        'Transferred',
        'Moved Out',
        'Abroad',
        'Deceased',
        'Archived',
    ];
}

function payment_statuses(): array
{
    return ['Pending', 'Approved', 'Rejected'];
}


function workflow_transitions(): array
{
    return [
        'Pending' => ['Under Review', 'Rejected', 'Cancelled'],
        'Under Review' => ['For Payment', 'For Approval', 'Processing', 'Returned for Correction', 'Rejected', 'Cancelled'],
        'For Payment' => ['Under Review', 'For Approval', 'Cancelled'],
        'For Approval' => ['Processing', 'Returned for Correction', 'Rejected'],
        'Processing' => ['Ready for Release', 'Completed', 'Cancelled'],
        'Ready for Release' => ['Completed'],
        'Returned for Correction' => ['Pending', 'Cancelled'],
        'Completed' => ['Archived'],
        'Rejected' => ['Archived'],
        'Cancelled' => ['Archived'],
        'Archived' => [],
    ];
}


/** Document / case categories and the fee schedule (PHP) for each document type. */
function module_categories(string $slug): array
{
    $map = [
        'documents' => [
            'Barangay Clearance' => 50.00,
            'Certificate of Indigency' => 0.00,
            'Certificate of Residency' => 50.00,
            'Business Permit Clearance' => 150.00,
            'Good Moral Certificate' => 0.00,
            'Low Income Certificate' => 0.00,
            'No Property Certificate' => 0.00,
        ],
        'complaints' => [
            'Blotter' => 0.00,
            'Complaint' => 0.00,
            'Katarungang Pambarangay' => 0.00,
            'VAWC' => 0.00,
            'BCPC' => 0.00,
        ],
    ];

    return $map[$slug] ?? [];
}

/** Fee for a document type. Residents cannot set their own fee. */
function document_fee(string $category): float
{
    return (float) (module_categories('documents')[$category] ?? 0.0);
}

function payment_methods(): array
{
    return ['Cash', 'E-Wallet', 'Bank Transfer'];
}

/** Barangay positions an official can request when signing up, mapped to a system role. */
function official_positions(): array
{
    return [
        'Barangay Captain' => 'punong_barangay',
        'Barangay Kagawad' => 'staff',
        'Barangay Secretary' => 'secretary',
        'Barangay Treasurer' => 'treasurer',
        'SK Chairperson' => 'staff',
        'SK Kagawad' => 'staff',
    ];
}

function release_statuses(): array
{
    return ['Approved', 'Ready for Release', 'Completed'];
}
