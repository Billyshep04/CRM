<?php

namespace Tests\Unit;

use App\Jobs\ProcessWebsiteLaunch;
use App\Jobs\ProcessWebsiteProvisioning;
use PHPUnit\Framework\TestCase;

class LongRunningHostingJobsTest extends TestCase
{
    /**
     * Both jobs can run for minutes (WHM account creation, WordPress install
     * over SSH, DNS/SSL polling). A real queue worker kills a job after 60
     * seconds by default, which would silently fail an otherwise-successful
     * run. These must declare a generous timeout so that, once queued for
     * real, the worker's default doesn't reintroduce the same false failure
     * this fix is meant to remove.
     */
    public function test_provisioning_job_declares_a_timeout_longer_than_a_realistic_run(): void
    {
        $job = new ProcessWebsiteProvisioning(1);

        $this->assertGreaterThanOrEqual(300, $job->timeout);
        $this->assertSame(1, $job->tries);
    }

    public function test_launch_job_declares_a_timeout_longer_than_a_realistic_run(): void
    {
        $job = new ProcessWebsiteLaunch(1);

        $this->assertGreaterThanOrEqual(300, $job->timeout);
        $this->assertSame(1, $job->tries);
    }
}
