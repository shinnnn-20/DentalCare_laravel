<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $referenceServices = [
            ['Consultation', 'Consultation and Basic Services', '₱500'],
            ['Dental Certificate', 'Consultation and Basic Services', '₱500'],
            ['Periapical X-Ray', 'Consultation and Basic Services', '₱600–₱800'],
            ['Extra Anesthesia', 'Consultation and Basic Services', '₱300–₱500'],
            ['Oral Prophylaxis — Mild', 'Oral Prophylaxis and Preventive Treatments', '₱1,000–₱1,500'],
            ['Oral Prophylaxis — Moderate', 'Oral Prophylaxis and Preventive Treatments', '₱1,500–₱2,000'],
            ['Oral Prophylaxis — Severe', 'Oral Prophylaxis and Preventive Treatments', '₱3,000'],
            ['Deep Scaling (Per Quadrant)', 'Oral Prophylaxis and Preventive Treatments', '₱3,500–₱4,000 per quadrant'],
            ['Fluoride Gel', 'Oral Prophylaxis and Preventive Treatments', '₱1,500–₱2,000'],
            ['Fluoride Varnish', 'Oral Prophylaxis and Preventive Treatments', '₱1,500–₱2,000'],
            ['Sealants', 'Oral Prophylaxis and Preventive Treatments', '₱1,000'],
            ['Diastema Closure', 'Restorative Treatments and Fillings', '₱2,000 per side'],
            ['Temporary Dressing', 'Restorative Treatments and Fillings', '₱800–₱1,000'],
            ['Composite Filling (Per Surface)', 'Restorative Treatments and Fillings', '₱1,000 per surface'],
            ['Inlay (Composite)', 'Restorative Treatments and Fillings', '₱7,000–₱8,000'],
            ['Direct Composite', 'Restorative Treatments and Fillings', '₱6,000'],
            ['Indirect Composite', 'Restorative Treatments and Fillings', '₱10,000'],
            ['Indirect Porcelain', 'Restorative Treatments and Fillings', '₱18,000 and up'],
            ['Simple Extraction', 'Extraction and Surgical Procedures', '₱1,000–₱1,500'],
            ['Complicated Extraction', 'Extraction and Surgical Procedures', '₱3,000'],
            ['Odontectomy', 'Extraction and Surgical Procedures', '₱10,000 and up'],
            ['Apicoectomy', 'Extraction and Surgical Procedures', '₱10,000–₱15,000'],
            ['Frenectomy', 'Extraction and Surgical Procedures', '₱10,000–₱15,000'],
            ['Gingivectomy (Per Arch)', 'Extraction and Surgical Procedures', '₱15,000 per arch'],
            ['Acrylic / Plastic Crown', 'Crowns, Bridges, and Dental Implants', '₱5,000'],
            ['PFM Crown', 'Crowns, Bridges, and Dental Implants', '₱8,500–₱9,500'],
            ['PFM Crown (Tilted)', 'Crowns, Bridges, and Dental Implants', '₱12,000 and up'],
            ['All-Ceramic Porcelain (Per Piece)', 'Crowns, Bridges, and Dental Implants', '₱20,000 and up per piece'],
            ['Zirconia Ceramic Crown', 'Crowns, Bridges, and Dental Implants', '₱25,000 and up'],
            ['SS Crown', 'Crowns, Bridges, and Dental Implants', '₱4,000'],
            ['Bone Augmentation', 'Crowns, Bridges, and Dental Implants', '₱25,000 and up'],
            ['Dental Implant Screw', 'Crowns, Bridges, and Dental Implants', '₱45,000 and up'],
            ['Dental Implant Abutment', 'Crowns, Bridges, and Dental Implants', '₱45,000 and up'],
            ['Dental Implant Crown', 'Crowns, Bridges, and Dental Implants', '₱15,000 and up'],
            ['Total Implant (Screw, Abutment, Crown)', 'Crowns, Bridges, and Dental Implants', '₱105,000'],
            ['Recementation of Crown (Per Unit)', 'Crowns, Bridges, and Dental Implants', '₱1,000 per unit'],
            ['Space Maintainer (Uni)', 'Space Maintainers', '₱3,500'],
            ['Space Maintainer (Bila)', 'Space Maintainers', '₱8,000–₱10,000'],
            ['In-Office Teeth Whitening (U/L)', 'Teeth Whitening', '₱25,000'],
            ['At-Home Teeth Whitening (U/L)', 'Teeth Whitening', '₱15,000'],
            ['Direct Composite Veneers', 'Veneers', '₱6,000'],
            ['Indirect Composite Veneers', 'Veneers', '₱10,000'],
            ['Indirect Porcelain Veneers', 'Veneers', '₱18,000 and up'],
            ['Root Canal — Mono-Rooted Tooth (Anterior)', 'Endodontic Treatments', '₱9,000'],
            ['Root Canal — Multi-Rooted Tooth (Per Canal)', 'Endodontic Treatments', '₱7,000 and up per canal'],
            ['Post and Core Build-Up (Fiber Post)', 'Endodontic Treatments', '₱5,000'],
            ['Metal Brackets', 'Orthodontic Treatments', '₱50,000 and up'],
            ['Ceramic Brackets', 'Orthodontic Treatments', '₱80,000 and up'],
            ['Self-Ligating Metal Brackets', 'Orthodontic Treatments', '₱100,000 and up'],
            ['Self-Ligating Ceramic Brackets', 'Orthodontic Treatments', '₱120,000 and up'],
            ['Clear Aligners', 'Orthodontic Treatments', '₱250,000–₱300,000'],
            ['Functional Appliance', 'Orthodontic Treatments', '₱20,000–₱25,000'],
            ["Hawley's Retainer (U/L)", 'Orthodontic Treatments', '₱12,000'],
            ['Essix Retainers', 'Orthodontic Treatments', '₱14,000'],
            ['Fixed Retainers', 'Orthodontic Treatments', '₱10,000 and up'],
            ['Temporary Anchorage Device', 'Orthodontic Treatments', '₱8,000'],
            ['Bracket Recementation', 'Orthodontic Treatments', '₱1,000–₱1,500'],
            ['Bracket Removal', 'Orthodontic Treatments', '₱10,000'],
            ['Complete Denture', 'Complete Dentures', '₱45,000 and up'],
            ['One Piece Uni', 'Complete Dentures', '₱12,000'],
            ['One Piece Bila', 'Complete Dentures', '₱15,000 and up'],
            ['New Acrylic Upper/Lower Denture', 'Partial Dentures', '₱16,000'],
            ['New Acrylic Full Set', 'Complete Dentures', '₱20,000'],
            ['Natura Tone (U/L)', 'Partial Dentures', '₱18,000 and up'],
            ['Natura Tone (US) (U/L)', 'Partial Dentures', '₱24,000'],
            ['Biotone (U/L)', 'Partial Dentures', '₱26,000'],
            ['Bioform (U/L)', 'Partial Dentures', '₱26,000'],
            ['Ivocap (U/L)', 'Partial Dentures', '₱60,000'],
            ['Thermosens (U/L)', 'Partial Dentures', '₱60,000'],
            ['Partial Denture — Stayplate (2 Pontics)', 'Partial Dentures', '₱4,000 and up (2 pontics; additional pontic +₱500)'],
            ['Assembled Casted Clasp', 'Partial Dentures', '₱4,000 plus ₱1,500 per casted clasp'],
            ['Flexible Uni', 'Partial Dentures', '₱15,000'],
            ['Flexible Bila', 'Partial Dentures', '₱20,000 and up'],
            ['Goldmesh (Additional)', 'Partial Dentures', '₱2,500 and up'],
            ['Lucitone (Add In)', 'Partial Dentures', '₱2,500 and up'],
            ['Flexi Combi (Bilateral)', 'Partial Dentures', '₱30,000'],
            ['Reline (Laboratory)', 'Denture Repairs and Other Treatments', '₱3,000–₱4,000'],
            ['Rebase (Laboratory)', 'Denture Repairs and Other Treatments', '50% of the denture cost'],
            ['Chairside Reline', 'Denture Repairs and Other Treatments', '₱1,500'],
            ['Night Guard (Per Arch)', 'Denture Repairs and Other Treatments', '₱7,000 per arch'],
        ];

        foreach ($referenceServices as [$name, $category, $priceDisplay]) {
            $service = Service::firstOrNew(['name' => $name]);

            if (! $service->exists) {
                $service->price = 0;
                $service->is_active = true;
            }

            $service->category = $category;

            if ($service->price_display === null) {
                $service->price_display = $priceDisplay;
                $service->requires_price_confirmation = true;
            }

            $service->save();
        }

        Service::query()
            ->whereIn('name', ['Dental Cleaning', 'Tooth Extraction', 'Dental Filling', 'Root Canal', 'Dental X-Ray', 'Teeth Whitening'])
            ->whereNull('price_display')
            ->update(['is_active' => false]);
    }
}
