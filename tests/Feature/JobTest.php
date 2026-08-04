<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create an admin user for testing
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);
        
        // Create a view-only user
        $this->viewerUser = User::factory()->create([
            'role' => 'sa', // Service Advisor can only view
            'email' => 'viewer@test.com',
        ]);
    }

    /**
     * Test that authenticated users can view jobs list
     */
    public function test_authenticated_user_can_view_jobs_list(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/jobs');

        $response->assertStatus(200);
        $response->assertViewIs('jobs.index');
    }

    /**
     * Test that unauthenticated users are redirected to login
     */
    public function test_unauthenticated_user_cannot_view_jobs(): void
    {
        $response = $this->get('/jobs');

        $response->assertRedirect('/login');
    }

    /**
     * Test that admin can access job create page
     */
    public function test_admin_can_access_job_create(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/jobs/create');

        $response->assertStatus(200);
        $response->assertViewIs('jobs.create');
    }

    /**
     * Test that SA cannot access job create page
     */
    public function test_sa_cannot_access_job_create(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/jobs/create');

        $response->assertStatus(403); // Forbidden
    }

    /**
     * Test that admin can create a job
     */
    public function test_admin_can_create_job(): void
    {
        $jobData = [
            'job_number' => 'WIP-TEST-001',
            'franchise' => 'PC',
            'plate_number' => 'B 1234 ABC',
            'customer_name' => 'Test Customer',
            'service_advisor' => 'Test SA',
            'job_date' => now()->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->adminUser)->post('/jobs', $jobData);

        $response->assertRedirect();
        $this->assertDatabaseHas('jobs', [
            'job_number' => 'WIP-TEST-001',
            'plate_number' => 'B 1234 ABC',
        ]);
    }

    /**
     * Test that job detail page is accessible
     */
    public function test_user_can_view_job_detail(): void
    {
        $job = Job::factory()->create();

        $response = $this->actingAs($this->adminUser)->get("/jobs/{$job->id}");

        $response->assertStatus(200);
        $response->assertViewIs('jobs.show');
    }

    /**
     * Test that admin can update a job
     */
    public function test_admin_can_update_job(): void
    {
        $job = Job::factory()->create([
            'customer_name' => 'Original Customer',
        ]);

        $response = $this->actingAs($this->adminUser)->put("/jobs/{$job->id}", [
            'job_number' => $job->job_number,
            'franchise' => 'PC',
            'plate_number' => $job->plate_number,
            'customer_name' => 'Updated Customer',
            'job_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'customer_name' => 'Updated Customer',
        ]);
    }

    /**
     * Test that SA cannot delete a job
     */
    public function test_sa_cannot_delete_job(): void
    {
        $job = Job::factory()->create();

        $response = $this->actingAs($this->viewerUser)->delete("/jobs/{$job->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
    }

    /**
     * Test that admin can delete a job
     */
    public function test_admin_can_delete_job(): void
    {
        $job = Job::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete("/jobs/{$job->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
    }

    /**
     * Test that creating a PartOrder from a job sets the job.rq field
     */
    public function test_creating_part_order_from_job_updates_job_rq(): void
    {
        $job = Job::factory()->create([
            'need_part' => true,
            'work_status' => '1. Belum diproses (Tunggu Antrian)',
        ]);

        $payload = [
            'job_id' => $job->id,
            'rq' => 'RQ-12345',
            'notes' => 'Test RQ',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('part-orders.create-from-job'), $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'rq' => 'RQ-12345',
        ]);
        $this->assertDatabaseHas('part_orders', [
            'job_id' => $job->id,
            'rq' => 'RQ-12345',
        ]);
    }
}
