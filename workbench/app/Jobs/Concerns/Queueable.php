<?php

namespace Workbench\App\Jobs\Concerns;

use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Laravel 11 combined these queue traits into Illuminate\Foundation\Queue\Queueable,
// which does not exist on Laravel 10. This mirrors it so the workbench jobs run on both.
trait Queueable
{
    use BusQueueable;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;
}
