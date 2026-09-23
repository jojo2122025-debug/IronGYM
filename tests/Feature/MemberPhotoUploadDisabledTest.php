<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberPhotoUploadDisabledTest extends TestCase
{
    use RefreshDatabase;

    private array $staffSession = ['user' => ['id' => 1, 'role' => 'مدير النظام']];

    public function test_member_can_be_added_without_a_photo_but_photo_upload_is_rejected(): void
    {
        $this->withSession($this->staffSession)->post('/api/add_member', [
            'name' => 'عضو جديد',
            'phone' => '0591234567',
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('members', 0);

        $response = $this->withSession($this->staffSession)->post('/api/add_member', [
            'name' => 'عضو جديد',
            'phone' => '0591234567',
        ])->assertOk();
        $this->assertNull(Member::findOrFail($response->json('id'))->image_path);
    }

    public function test_edit_preserves_an_existing_photo_and_rejects_replacement_upload(): void
    {
        Member::create([
            'id' => 'M00001', 'name' => 'عضو قديم', 'phone' => '0591234567',
            'image_path' => 'uploads/existing.jpg',
        ]);

        $this->withSession($this->staffSession)->post('/api/edit_member', [
            'id' => 'M00001', 'name' => 'عضو محدث', 'phone' => '0591234567',
            'image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertUnprocessable();

        $this->withSession($this->staffSession)->post('/api/edit_member', [
            'id' => 'M00001', 'name' => 'عضو محدث', 'phone' => '0591234567',
        ])->assertOk();
        $this->assertSame('uploads/existing.jpg', Member::findOrFail('M00001')->image_path);
    }
}
