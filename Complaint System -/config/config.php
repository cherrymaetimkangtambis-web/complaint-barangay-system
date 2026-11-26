<?php
// ============================================================
//  SETTINGS  - edit here if needed
// ============================================================

// Database (default XAMPP values)
define('DB_HOST', 'localhost');
define('DB_NAME', 'barangay_complaint_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// App info
define('APP_NAME', 'Barangay Complaint Management System');
define('BARANGAY_NAME', 'Barangay San Roque');
define('BARANGAY_PLACE', 'Tomas Oppus, Southern Leyte');
define('BARANGAY_CONTACT_NUMBER', '');
define('BARANGAY_EMAIL', '');
define('BARANGAY_OFFICE_HOURS', '');
define('PRIVACY_NOTICE_VERSION', '2026-11-v1');

const BARANGAY_OFFICIALS = [];
const PUBLIC_FAQ = [
    [
        'question' => 'How do I file a complaint?',
        'answer' => 'Register or log in, choose File Complaint, and provide the category, description, and location.',
    ],
    [
        'question' => 'How can I check an update?',
        'answer' => 'Log in and open My Complaints. Your reference number and status history are shown there.',
    ],
    [
        'question' => 'What happens to my report?',
        'answer' => 'Authorized barangay staff review the report and update its status. Use the contact details on this page for urgent concerns.',
    ],
];

// Security / limits
define('SESSION_TIMEOUT', 1800);              // 30 minutes of no activity = auto logout
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);  // 2 MB photo limit
define('MAX_SITE_IMAGE_BYTES', 5 * 1024 * 1024);
define('PER_PAGE', 10);                       // rows per page in tables

// Leave empty to auto-detect the folder name (e.g. /complaint-system).
// Only set this if links are broken, e.g. define('BASE_URL_OVERRIDE', '/complaint-system');
define('BASE_URL_OVERRIDE', '');

// Complaint categories
const CATEGORIES = [
    'Garbage / Sanitation',
    'Noise Disturbance',
    'Road / Infrastructure',
    'Water / Drainage',
    'Street Lights / Electricity',
    'Peace and Order',
    'Stray Animals',
    'Others',
];

// List of puroks in Barangay San Roque  -> EDIT THIS to match your real puroks
const PUROKS = [
    'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7',
];

const STATUSES   = ['Pending', 'In Progress', 'Resolved', 'Cancelled'];
const PRIORITIES = ['Low', 'Medium', 'Urgent'];
const MEDIATION_STATUSES = ['Pending', 'Scheduled', 'Summons issued', 'Summons served', 'Settled', 'Unsettled', 'Closed'];
