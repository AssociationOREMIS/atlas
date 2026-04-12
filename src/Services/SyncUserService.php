<?php

namespace Oremis\Atlas\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;


class SyncUserService
{
    public function sync(array $profile, $googleUser)
    {
        $model = config('atlas.user_model');
        $seniority = $profile['seniority'] ?? null;
        $qualification = $profile['qualification'] ?? null;

        $seniorityFormatted = null;
        if (
            is_array($seniority)
            && isset($seniority['years'], $seniority['months'], $seniority['days'])
        ) {
            $seniorityFormatted = sprintf(
                '%d ans, %d mois, %d jours',
                $seniority['years'],
                $seniority['months'],
                $seniority['days']
            );
        }

        $qualificationData = null;
        if (is_array($qualification)) {
            $qualificationData = [
                'id' => $qualification['id'] ?? null,
                'code' => $qualification['code'] ?? null,
                'name' => $qualification['name'] ?? null,
                'order' => $qualification['order'] ?? null,
            ];
        } elseif (is_object($qualification)) {
            $qualificationData = [
                'id' => $qualification->id ?? null,
                'code' => $qualification->code ?? null,
                'name' => $qualification->name ?? null,
                'order' => $qualification->order ?? null,
            ];
        }

        // the most important identity
        $cib = $profile['cib'] ?? null;

        // google fallback
        $googleId = $profile['google_id'] ?? $googleUser->id ?? null;

        // email fallback
        $email = $profile['email'] ?? $googleUser->email ?? null;

        // ALWAYS try CIB first
        $user = null;

        if ($cib) {
            $user = $model::where('cib', $cib)->first();
        }

        // fallback (google_id)
        if (!$user && $googleId) {
            $user = $model::where('google_id', $googleId)->first();
        }

        // fallback (email)
        if (!$user && $email) {
            $user = $model::where('email', $email)->first();
        }

        // data coming from PIO
        $data = [
            'cib' => $cib,
            'google_id' => $googleId,
            'email' => $email,
            'first_name' => $profile['first_name'] ?? $googleUser->user['given_name'] ?? null,
            'last_name' => $profile['last_name'] ?? $googleUser->user['family_name'] ?? null,
            'status' => $profile['status'] ?? ($user->status ?? 'active'),
            'profile_data' => [
                'staff_positions'   => $profile['staff_positions'] ?? [],
                'departments'       => $profile['departments'] ?? [],
                'department_teams'  => $profile['department_teams'] ?? [],
                'is_supervisor'      => $profile['is_supervisor'] ?? ($user->is_supervisor ?? false),
                'is_certified_examiner' => $profile['is_certified_examiner'] ?? ($user->is_certified_examiner ?? false),
                'is_social_officer' => $profile['is_social_officer'] ?? ($user->is_social_officer ?? false),
                'seniority' => $seniority,
                'seniority_formatted' => $seniorityFormatted,
                'qualification_id' => $profile['qualification_id'] ?? ($qualificationData['id'] ?? null),
                'qualification' => $qualificationData,
            ],
            'last_login_at' => Carbon::now(),
        ];

        if ($user) {
            $user->fill($data)->save();
        } else {
            if (Schema::hasColumn((new $model)->getTable(), 'password')) {
                $data['password'] = bcrypt(Str::random(40));
            }

            $user = $model::create($data);
        }

        return $user;
    }
}
