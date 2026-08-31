<?php

return [
    'event_name' => env('VMS_EVENT_NAME', 'Traction Guest'),
    'participant_category_code' => env('VMS_PARTICIPANT_CATEGORY_CODE', 'participant'),
    'media_disk' => env('VISITOR_MEDIA_DISK', 'visitor-media'),
    'category_card_disk' => env('VISITOR_CATEGORY_CARD_DISK', 'public'),
];
