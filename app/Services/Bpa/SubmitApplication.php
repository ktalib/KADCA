<?php

namespace App\Services\Bpa;

use App\Models\Bpa\Application;
use App\Models\User;
use App\Services\Klaes\IndexedFiles;
use App\Services\Serials;
use App\Support\BpaForm;
use App\Support\LandUse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves a Building Plan Approval application at the Assessment Stage: the land-file snapshot from KLAES,
 * the land-use check, the uploaded plans and the application bill (rate × plots). Application and bill
 * numbers are yearly serials, e.g. BPA-APP/2026/00001 and BPA-BILL/2026/00001.
 */
class SubmitApplication
{
    public function __construct(private IndexedFiles $files, private Serials $serials)
    {
    }

    /**
     * @param  array<string, mixed>  $data  validated form input
     * @param  list<UploadedFile>  $uploads
     */
    public function handle(array $data, array $uploads, User $officer): Application
    {
        // Property details always come from KLAES, never from the posted form.
        $file = $this->files->find((int) $data['klaes_file_indexing_id']);
        if ($file === null) {
            throw ValidationException::withMessages([
                'klaes_file_indexing_id' => 'That land file was not found in KLAES (or it is deleted or decommissioned). Pick the file again.',
            ]);
        }

        [$check, $note] = LandUse::check($data['proposed_use'], $file['land_use_code']);
        $rate = (int) config('kadca.application_fee_per_plot');
        $plots = (int) $data['number_of_plots'];
        $disk = Storage::disk(config('kadca.uploads_disk'));
        $stored = [];

        try {
            return DB::transaction(function () use ($data, $uploads, $officer, $file, $check, $note, $rate, $plots, $disk, &$stored) {
                $year = (int) now()->format('Y');
                $applicationNo = sprintf('BPA-APP/%d/%05d', $year, $this->serials->next('bpa_application', $year));

                $application = Application::create([
                    'application_no' => $applicationNo,
                    'status' => 'Awaiting payment',
                    'klaes_file_indexing_id' => $file['id'],
                    'file_number' => $file['file_number'],
                    'file_title' => $file['file_title'],
                    'plot_no' => $file['plot_no'],
                    'district' => $file['district'],
                    'lga' => $file['lga'],
                    'location' => $file['location'],
                    'plot_size' => $file['plot_size'],
                    'file_land_use' => $file['land_use'],
                    'file_land_use_code' => $file['land_use_code'],
                    'title_status' => $file['title_status'],
                    'proposed_use' => $data['proposed_use'],
                    'proposed_use_other' => $data['proposed_use'] === LandUse::OTHER ? $data['proposed_use_other'] : null,
                    'land_use_check' => $check,
                    'land_use_note' => $note,
                    'developer_name' => $data['developer_name'],
                    'cadastral_zone' => $data['cadastral_zone'] ?? null,
                    'cadastral_zone_plot_no' => $data['cadastral_zone_plot_no'] ?? null,
                    'street_name' => $data['street_name'] ?? null,
                    'dwelling_plot_floor' => $data['dwelling_plot_floor'] ?? null,
                    'dwelling_unit' => $data['dwelling_unit'] ?? null,
                    'contact_address' => $data['contact_address'] ?? null,
                    'developer_phone' => $data['developer_phone'] ?? null,
                    'developer_email' => $data['developer_email'] ?? null,
                    'representative_phone' => $data['representative_phone'] ?? null,
                    'representative_email' => $data['representative_email'] ?? null,
                    'submission_type' => $data['submission_type'],
                    'submission_type_other' => $data['submission_type'] === BpaForm::OTHER ? $data['submission_type_other'] : null,
                    'documents_submitted' => array_values($data['documents'] ?? []),
                    'documents_other' => in_array(BpaForm::OTHER, $data['documents'] ?? [], true) ? $data['documents_other'] : null,
                    'drawings_submitted' => array_values($data['drawings'] ?? []),
                    'plan_received_as' => $data['plan_received_as'],
                    'number_of_plots' => $plots,
                    'submitted_by_name' => $data['submitted_by_name'] ?? null,
                    'submitted_on' => $data['submitted_on'] ?? null,
                    'received_by' => $officer->getKey(),
                    'received_by_name' => $officer->name,
                    'received_by_rank' => $officer->rank,
                ]);

                $folder = 'kadca/bpa-applications/'.Str::slug(str_replace('/', '-', $applicationNo));
                foreach ($uploads as $upload) {
                    $ext = strtolower($upload->getClientOriginalExtension());
                    $path = $upload->storeAs($folder, Str::uuid().'.'.$ext, ['disk' => config('kadca.uploads_disk')]);
                    $stored[] = $path;

                    $application->files()->create([
                        'kind' => in_array($ext, BpaForm::CAD_EXTENSIONS, true) ? 'cad' : 'plan',
                        'original_name' => mb_substr($upload->getClientOriginalName(), 0, 300),
                        'stored_path' => $path,
                        'extension' => $ext,
                        'size_bytes' => $upload->getSize(),
                        'uploaded_by' => $officer->getKey(),
                    ]);
                }

                $application->bill()->create([
                    'bill_no' => sprintf('BPA-BILL/%d/%05d', $year, $this->serials->next('bpa_bill', $year)),
                    'bill_type' => 'BPA Application Fee',
                    'rate' => $rate,
                    'quantity' => $plots,
                    'amount' => $rate * $plots,
                    'status' => 'Unpaid',
                    'created_by' => $officer->getKey(),
                ]);

                return $application;
            });
        } catch (\Throwable $e) {
            // Nothing was saved, so don't leave the uploaded files behind.
            foreach ($stored as $path) {
                $disk->delete($path);
            }
            throw $e;
        }
    }
}
