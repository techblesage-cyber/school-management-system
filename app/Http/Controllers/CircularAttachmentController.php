<?php

namespace App\Http\Controllers;

use App\Models\Circular;
use App\Models\ParentAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a circular's attachment, for staff who may read the circular
 * and for parents it was sent to. Attachments aren't public files.
 */
class CircularAttachmentController extends Controller
{
    public function __invoke(Request $request, Circular $circular): StreamedResponse
    {
        abort_unless(filled($circular->attachment) && $this->allowed($request, $circular), 403);
        abort_unless(Storage::disk('local')->exists($circular->attachment), 404);

        return Storage::disk('local')->download($circular->attachment, $circular->attachment_name ?: basename($circular->attachment));
    }

    protected function allowed(Request $request, Circular $circular): bool
    {
        $staff = $request->user();

        if ($staff instanceof User && Gate::forUser($staff)->allows('view', $circular)) {
            return true;
        }

        $parent = class_exists(ParentAccount::class) ? Auth::guard('parent')->user() : null;

        return $parent instanceof ParentAccount
            && Circular::query()->withoutGlobalScopes()->whereKey($circular->getKey())->forParentsOf($parent->children())->exists();
    }
}
