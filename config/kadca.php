<?php

return [

    /*
    | Building Plan Approval application fee, per plot. The KADCA application form says
    | "₦15,000 per plot, non-refundable". Kept as a setting until KADCA confirms whether it is a
    | fixed amount or a rate managed under Configurable Entities.
    */
    'application_fee_per_plot' => (int) env('KADCA_APPLICATION_FEE_PER_PLOT', 15000),

    /*
    | Request purposes for Log a File and Quick Search, in KLAES's request_purposes shape (name => turnaround
    | days). The purpose sets the timeline and expected return date and the Green / Amber / Red status.
    */
    'request_purposes' => [
        'FOR RECOMMENDATION' => 5,
        'FOR APPROVAL' => 5,
        'FOR SITE INSPECTION' => 7,
        'FOR ASSESSMENT' => 5,
        'FOR BILLING' => 3,
        'FOR CONVEYANCE' => 5,
        'FOR MEMO' => 3,
        'FOR REFERENCE' => 3,
        'FOR FILING' => 2,
    ],

    // Uploaded building plans (scanned booklet, soft copy, DXF/DWG). Local for now; the briefing
    // puts KADCA's own files on a separate file server later.
    'uploads_disk' => env('KADCA_UPLOADS_DISK', 'local'),
    'upload_max_kb' => (int) env('KADCA_UPLOAD_MAX_KB', 102400),

    // DMS: scanned pages of indexed (shadow) files. Same disk as the uploads until KADCA's file server is set up.
    'dms_disk' => env('KADCA_DMS_DISK', env('KADCA_UPLOADS_DISK', 'local')),
    'dms_page_max_kb' => (int) env('KADCA_DMS_PAGE_MAX_KB', 51200),

    /*
    | Page types KADCA adds to KLAES's own list (dbo.PageType / PageSubType) for Page Typing: the documents and
    | drawings named on the KADCA application form and the KADCA FORM templates. Merged into KLAES's list by name,
    | so 'Form' and 'Report' gain these subtypes rather than appearing twice.
    */
    'dms_page_types' => [
        'Building Plan' => ['Architectural Drawing', 'Structural Drawing', 'Electrical Drawing', 'Mechanical Drawing',
            'Site Plan', 'Contoured Site Plan', 'Calculation Sheets', 'CAD Drawing Printout'],
        'Form' => ['BPA Application Form', 'Building Plans Assessment Form', 'Development Control Assessment Form',
            'Fast-Track Planning Permit Application', 'Final Certificate of Completion Application',
            'Major Improvement / Renovation Application', 'Outdoor Advertisement / Signage Application',
            'Redevelopment Permit Application'],
        'Report' => ['Site Analysis Report', 'Site Inspection Report', 'EIAR', 'Fire Service Report', 'Police Report',
            'Soil / Geotechnical Test'],
        'Permit / Notice' => ['Development Permit', 'Commencement Notice', 'Decoration / Improvement Notice',
            'Contravention Notice', 'Observation on Contravention', 'Conveyance of Approval'],
        'Image' => ['Geocoded Site Photograph'],
    ],

    /*
    | Applicant portal (/portal). UI first (user 2026-11-23: "first finish the UI and frontend ... next we can focus on the
    | backend"): applicant accounts are not stored yet, so the portal runs on a session-only sign-in.
    | - preview: saves answer "Saved (preview)" and store nothing; an application can be opened by its number for a layout check.
    | - demo: a demo applicant with sample applications at every stage. On by default except in production.
    */
    'portal' => [
        'preview' => (bool) env('KADCA_PORTAL_PREVIEW', true),
        'demo_enabled' => (bool) env('KADCA_PORTAL_DEMO', env('APP_ENV') !== 'production'),
        'demo' => [
            'username' => 'demo.applicant',
            'password' => env('KADCA_PORTAL_DEMO_PASSWORD', 'kadca-demo'),
            'name' => 'Demo Applicant',
            'phone' => '08000000000', // placeholder: never texted (KLAES rule)
            'email' => null,
        ],

        // PLACEHOLDER until KADCA sends the real list: the stages an applicant reports while building.
        'construction_stages' => ['Setting out', 'Foundation', 'Damp-proof course (DPC)', 'Lintel level', 'Roofing', 'Finishing', 'Completed'],
        'progress_max_photos' => 10,
        'progress_photo_max_kb' => 10240,

        // PLACEHOLDER until KADCA confirms: when site inspections can be booked.
        'inspection' => [
            'slots' => ['09:00', '11:00', '14:00'],
            'weekdays' => [1, 2, 3, 4, 5], // ISO day numbers, Monday = 1
            'lead_days' => 2,               // earliest booking: today + this many days
            'horizon_days' => 30,           // latest booking: today + this many days
        ],
    ],

];
