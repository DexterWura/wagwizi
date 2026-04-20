<?php

namespace App\Services\Platform\Adapters;

use App\Services\Platform\Platform;

class FacebookPagesAdapter extends FacebookAdapter
{
    public function platform(): Platform
    {
        return Platform::FacebookPages;
    }
}
