<?php

namespace App\Services\Platform\Adapters;

use App\Services\Platform\Platform;

class LinkedInPagesAdapter extends LinkedInAdapter
{
    public function platform(): Platform
    {
        return Platform::LinkedInPages;
    }
}
