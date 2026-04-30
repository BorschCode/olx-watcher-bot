<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\City;
use App\Models\District;


#[Signature('app:import-olx-locations')]
#[Description('Command description')]

class ImportOlxLocations extends Command
{
    protected $signature = 'olx:import-locations';

    protected $description = 'Import OLX cities and districts';

    public function handle()
    {
        $data = config('olx_locations');

        foreach ($data['cities'] as $slug => $city) {

            $cityModel = City::updateOrCreate(
                ['slug' => $slug],
                ['name' => $city['display']]
            );

            foreach ($city['districts'] as $district => $id) {

                District::updateOrCreate(
                    [
                        'city_id' => $cityModel->id,
                        'olx_id' => $id
                    ],
                    [
                        'name' => $district
                    ]
                );
            }

            $this->info("City imported: {$city['display']}");
        }
    }
}
