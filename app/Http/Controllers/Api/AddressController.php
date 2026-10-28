<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $addresses = Address::query()
            ->where('user_id', auth('api')->id())
            ->orderByDesc('is_default')
            ->latest()
            ->orderByDesc('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        $address = DB::transaction(function () use ($data): Address {
            $user = $this->lockedUser();
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault || ! $user->addresses()->exists()) {
                $user->addresses()->update(['is_default' => false]);
                $isDefault = true;
            }

            return $user->addresses()->create([...$data, 'is_default' => $isDefault]);
        });

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function show(string $address): AddressResource
    {
        return new AddressResource($this->addressForCurrentUser((int) $address));
    }

    public function update(UpdateAddressRequest $request, string $address): AddressResource
    {
        $data = $request->validated();

        $updatedAddress = DB::transaction(function () use ($address, $data): Address {
            $user = $this->lockedUser();
            $address = $user->addresses()->whereKey($address)->lockForUpdate()->firstOrFail();
            $isDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $address->is_default;

            if ($isDefault) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update([...$data, 'is_default' => $isDefault]);

            return $address->fresh();
        });

        return new AddressResource($updatedAddress);
    }

    public function destroy(string $address): Response
    {
        DB::transaction(function () use ($address): void {
            $user = $this->lockedUser();
            $address = $user->addresses()->whereKey($address)->lockForUpdate()->firstOrFail();
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $user->addresses()
                    ->oldest()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first()?->update(['is_default' => true]);
            }
        });

        return response()->noContent();
    }

    public function makeDefault(string $address): AddressResource
    {
        $defaultAddress = DB::transaction(function () use ($address): Address {
            $user = $this->lockedUser();
            $address = $user->addresses()->whereKey($address)->lockForUpdate()->firstOrFail();
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });

        return new AddressResource($defaultAddress);
    }

    private function addressForCurrentUser(int $addressId): Address
    {
        return Address::query()
            ->whereKey($addressId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();
    }

    private function lockedUser(): User
    {
        return User::query()->whereKey(auth('api')->id())->lockForUpdate()->firstOrFail();
    }
}
