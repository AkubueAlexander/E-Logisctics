<?php
namespace App\Actions\User;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUserProfile
{
    public function execute(User $user, array $data, ?UploadedFile $photo = null): User
    {
        if ($photo) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $photo->store('profile-photos', 'public');
        }

        $user->update($data);

        return $user->refresh();
    }
}
