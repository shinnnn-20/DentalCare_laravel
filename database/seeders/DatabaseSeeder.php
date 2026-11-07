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

        $serviceDetails = [
            'Consultation' => ['description' => 'A dental examination and discussion of symptoms, oral health, and suitable treatment options.', 'duration_minutes' => 30],
            'Dental Certificate' => ['description' => 'A dental assessment and preparation of a certificate documenting the dentist’s findings.', 'duration_minutes' => 20],
            'Periapical X-Ray' => ['description' => 'A focused X-ray showing a tooth from crown to root and the surrounding bone to support diagnosis.', 'duration_minutes' => 10],
            'Extra Anesthesia' => ['description' => 'Additional local anesthetic administered when needed to keep a dental procedure comfortable.', 'duration_minutes' => 10],
            'Oral Prophylaxis — Mild' => ['description' => 'Routine cleaning to remove light plaque and tartar deposits, followed by polishing.', 'duration_minutes' => 30],
            'Oral Prophylaxis — Moderate' => ['description' => 'A more thorough cleaning for moderate plaque and tartar buildup, followed by polishing.', 'duration_minutes' => 40],
            'Oral Prophylaxis — Severe' => ['description' => 'Extended cleaning for heavier deposits; the dentist may recommend dividing care into visits.', 'duration_minutes' => 60],
            'Deep Scaling (Per Quadrant)' => ['description' => 'Scaling below the gumline in one section of the mouth to remove deposits associated with gum disease.', 'duration_minutes' => 60],
            'Fluoride Gel' => ['description' => 'A professionally applied fluoride gel to help strengthen enamel and reduce the risk of tooth decay.', 'duration_minutes' => 20],
            'Fluoride Varnish' => ['description' => 'A thin fluoride coating applied to the teeth to help protect enamel from decay.', 'duration_minutes' => 20],
            'Sealants' => ['description' => 'A protective coating placed in the grooves of selected teeth to help keep food and bacteria out.', 'duration_minutes' => 30],
            'Diastema Closure' => ['description' => 'Tooth-colored restorative material is shaped to close or reduce a space between teeth.', 'duration_minutes' => 50],
            'Temporary Dressing' => ['description' => 'A short-term protective filling placed to cover a tooth while it awaits further treatment.', 'duration_minutes' => 20],
            'Composite Filling (Per Surface)' => ['description' => 'A tooth-colored resin restoration used to repair decay or minor damage on one tooth surface.', 'duration_minutes' => 40],
            'Inlay (Composite)' => ['description' => 'A custom composite restoration fitted within a prepared tooth to replace a larger area of damage.', 'duration_minutes' => 60],
            'Direct Composite' => ['description' => 'Composite resin shaped directly on a tooth to restore its form, function, or appearance.', 'duration_minutes' => 60],
            'Indirect Composite' => ['description' => 'A custom-made composite restoration prepared outside the mouth and bonded to the tooth.', 'duration_minutes' => 60],
            'Indirect Porcelain' => ['description' => 'A custom porcelain restoration made outside the mouth and bonded to a prepared tooth.', 'duration_minutes' => 60],
            'Simple Extraction' => ['description' => 'Removal of a visible tooth that can be taken out without a surgical approach.', 'duration_minutes' => 30],
            'Complicated Extraction' => ['description' => 'Removal of a tooth requiring additional steps because of its position, condition, or surrounding tissue.', 'duration_minutes' => 60],
            'Odontectomy' => ['description' => 'A surgical procedure to remove an impacted or unerupted tooth, commonly a wisdom tooth.', 'duration_minutes' => 90],
            'Apicoectomy' => ['description' => 'A surgical procedure that removes the tip of a tooth root and treats the surrounding area when needed.', 'duration_minutes' => 90],
            'Frenectomy' => ['description' => 'Removal or release of a frenum, the small tissue fold connecting the lip or tongue to nearby tissue.', 'duration_minutes' => 60],
            'Gingivectomy (Per Arch)' => ['description' => 'Removal or reshaping of gum tissue in one arch when clinically indicated.', 'duration_minutes' => 90],
            'Acrylic / Plastic Crown' => ['description' => 'A prefabricated acrylic crown used to cover and protect a prepared tooth, often as a temporary restoration.', 'duration_minutes' => 60],
            'PFM Crown' => ['description' => 'A crown with a metal substructure and porcelain exterior, made to cover and restore a tooth.', 'duration_minutes' => 60],
            'PFM Crown (Tilted)' => ['description' => 'A porcelain-fused-to-metal crown adjusted for a tilted tooth or alignment, based on the dentist’s assessment.', 'duration_minutes' => 80],
            'All-Ceramic Porcelain (Per Piece)' => ['description' => 'A custom all-ceramic crown or restoration designed to cover and restore one prepared tooth.', 'duration_minutes' => 60],
            'Zirconia Ceramic Crown' => ['description' => 'A custom zirconia-based ceramic crown made to cover and restore a prepared tooth.', 'duration_minutes' => 60],
            'SS Crown' => ['description' => 'A stainless-steel crown that covers and protects a prepared tooth, often used for primary teeth.', 'duration_minutes' => 40],
            'Bone Augmentation' => ['description' => 'A grafting procedure that adds or supports bone at a site where additional bone may be needed.', 'duration_minutes' => 90],
            'Dental Implant Screw' => ['description' => 'Surgical placement of an implant fixture in the jaw to serve as support for a replacement tooth.', 'duration_minutes' => 90],
            'Dental Implant Abutment' => ['description' => 'Placement or fitting of the connector that links a dental implant fixture to its crown.', 'duration_minutes' => 40],
            'Dental Implant Crown' => ['description' => 'A custom crown fitted to an implant abutment to replace the visible part of a missing tooth.', 'duration_minutes' => 60],
            'Total Implant (Screw, Abutment, Crown)' => ['description' => 'A complete implant-supported tooth replacement plan; surgical and restorative stages are usually scheduled separately.', 'duration_minutes' => 120],
            'Recementation of Crown (Per Unit)' => ['description' => 'Cleaning and reattaching a suitable existing crown to its tooth with dental cement.', 'duration_minutes' => 30],
            'Space Maintainer (Uni)' => ['description' => 'A custom appliance that holds space on one side of the mouth after early loss of a tooth.', 'duration_minutes' => 40],
            'Space Maintainer (Bila)' => ['description' => 'A custom appliance that helps maintain space on both sides of the mouth after early tooth loss.', 'duration_minutes' => 50],
            'In-Office Teeth Whitening (U/L)' => ['description' => 'Professional whitening of the upper and/or lower natural teeth in the clinic after an assessment.', 'duration_minutes' => 90],
            'At-Home Teeth Whitening (U/L)' => ['description' => 'A dentist-supervised home whitening kit with fitted trays and instructions; whitening is completed at home over time.', 'duration_minutes' => 30],
            'Direct Composite Veneers' => ['description' => 'Tooth-colored composite resin shaped directly on the front of teeth to change their appearance.', 'duration_minutes' => 60],
            'Indirect Composite Veneers' => ['description' => 'Custom composite shells made outside the mouth and bonded to the front of selected teeth.', 'duration_minutes' => 60],
            'Indirect Porcelain Veneers' => ['description' => 'Custom porcelain shells bonded to the front of selected teeth to change their appearance.', 'duration_minutes' => 60],
            'Root Canal' => ['description' => 'Treatment that removes inflamed or infected tissue from inside a tooth, cleans the canals, and seals them; multiple visits may be needed.', 'duration_minutes' => 90],
            'Root Canal — Mono-Rooted Tooth (Anterior)' => ['description' => 'Root canal treatment for a front tooth with one root, including cleaning and sealing the canal.', 'duration_minutes' => 90],
            'Root Canal — Multi-Rooted Tooth (Per Canal)' => ['description' => 'Root canal treatment for a tooth with multiple canals; treatment may be staged across appointments.', 'duration_minutes' => 120],
            'Post and Core Build-Up (Fiber Post)' => ['description' => 'A fiber post and core are placed to support restoration of a tooth with substantial structure loss.', 'duration_minutes' => 60],
            'Metal Brackets' => ['description' => 'Fixed orthodontic metal brackets bonded to teeth as part of a dentist-planned alignment treatment.', 'duration_minutes' => 120],
            'Ceramic Brackets' => ['description' => 'Fixed tooth-colored ceramic brackets bonded to teeth as part of an orthodontic alignment plan.', 'duration_minutes' => 120],
            'Self-Ligating Metal Brackets' => ['description' => 'Metal orthodontic brackets with an integrated clip mechanism, fitted as part of an alignment plan.', 'duration_minutes' => 120],
            'Self-Ligating Ceramic Brackets' => ['description' => 'Tooth-colored orthodontic brackets with an integrated clip mechanism, fitted as part of an alignment plan.', 'duration_minutes' => 120],
            'Clear Aligners' => ['description' => 'A series of removable clear trays planned to gradually move teeth, with progress reviewed by the dentist.', 'duration_minutes' => 60],
            'Functional Appliance' => ['description' => 'A removable or fixed orthodontic appliance used to guide jaw or bite development when appropriate.', 'duration_minutes' => 50],
            "Hawley's Retainer (U/L)" => ['description' => 'A removable wire-and-acrylic retainer custom-fitted to help maintain tooth position after orthodontic treatment.', 'duration_minutes' => 30],
            'Essix Retainers' => ['description' => 'A clear removable retainer formed to fit over the teeth and help maintain their position.', 'duration_minutes' => 30],
            'Fixed Retainers' => ['description' => 'A thin wire bonded behind selected teeth to help maintain their position after orthodontic treatment.', 'duration_minutes' => 40],
            'Temporary Anchorage Device' => ['description' => 'A small temporary anchorage device placed to provide support for selected orthodontic tooth movements.', 'duration_minutes' => 40],
            'Bracket Recementation' => ['description' => 'A loose orthodontic bracket is cleaned and rebonded to the tooth when suitable.', 'duration_minutes' => 30],
            'Bracket Removal' => ['description' => 'Removal of orthodontic brackets and adhesive, followed by cleaning of the tooth surfaces.', 'duration_minutes' => 60],
            'Complete Denture' => ['description' => 'A removable appliance that replaces all teeth in an upper or lower arch; impressions, try-in, and fitting usually require separate visits.', 'duration_minutes' => 60],
            'One Piece Uni' => ['description' => 'A single-tooth removable partial denture option for replacing one missing tooth on one side.', 'duration_minutes' => 40],
            'One Piece Bila' => ['description' => 'A removable partial denture option designed to replace missing teeth on both sides of an arch.', 'duration_minutes' => 50],
            'New Acrylic Upper/Lower Denture' => ['description' => 'A new acrylic removable denture for the upper or lower arch, made through impressions, fitting, and adjustment visits.', 'duration_minutes' => 60],
            'New Acrylic Full Set' => ['description' => 'A new upper and lower acrylic denture set made through impressions, fitting, and adjustment visits.', 'duration_minutes' => 60],
            'Natura Tone (U/L)' => ['description' => 'A removable denture made with Natura Tone material for the upper or lower arch; fitting is completed over visits.', 'duration_minutes' => 50],
            'Natura Tone (US) (U/L)' => ['description' => 'An upper or lower removable denture option using the specified Natura Tone material and shade.', 'duration_minutes' => 50],
            'Biotone (U/L)' => ['description' => 'An upper or lower removable denture option using Biotone denture teeth/material, fitted to the patient.', 'duration_minutes' => 50],
            'Bioform (U/L)' => ['description' => 'An upper or lower removable denture option using Bioform denture teeth/material, fitted to the patient.', 'duration_minutes' => 50],
            'Ivocap (U/L)' => ['description' => 'An upper or lower denture fabricated with the specified Ivocap processing system and fitted over visits.', 'duration_minutes' => 50],
            'Thermosens (U/L)' => ['description' => 'An upper or lower removable denture made with Thermosens material and fitted to the patient.', 'duration_minutes' => 50],
            'Partial Denture — Stayplate (2 Pontics)' => ['description' => 'A removable stayplate replacing two missing teeth; additional teeth may require a revised design.', 'duration_minutes' => 50],
            'Assembled Casted Clasp' => ['description' => 'A cast metal clasp component fitted as part of a removable partial denture.', 'duration_minutes' => 40],
            'Flexible Uni' => ['description' => 'A flexible removable partial denture designed to replace missing teeth on one side of an arch.', 'duration_minutes' => 50],
            'Flexible Bila' => ['description' => 'A flexible removable partial denture designed to replace missing teeth on both sides of an arch.', 'duration_minutes' => 60],
            'Goldmesh (Additional)' => ['description' => 'A gold mesh component added to a denture design when selected by the dentist and patient.', 'duration_minutes' => 30],
            'Lucitone (Add In)' => ['description' => 'An additional Lucitone denture material component incorporated into a denture when specified.', 'duration_minutes' => 30],
            'Flexi Combi (Bilateral)' => ['description' => 'A bilateral flexible-combination partial denture designed to replace teeth on both sides of an arch.', 'duration_minutes' => 60],
            'Reline (Laboratory)' => ['description' => 'A denture is sent for laboratory relining to improve how its base fits the supporting tissues; a visit is needed for assessment and impressions.', 'duration_minutes' => 40],
            'Rebase (Laboratory)' => ['description' => 'A laboratory procedure that replaces the denture base while retaining the existing denture teeth.', 'duration_minutes' => 40],
            'Chairside Reline' => ['description' => 'The fitting surface of a denture is relined in the clinic to improve its fit against the supporting tissues.', 'duration_minutes' => 60],
            'Night Guard (Per Arch)' => ['description' => 'A custom protective appliance for one arch, usually made from a scan or impression and fitted at a later visit.', 'duration_minutes' => 30],
            'Dental Cleaning' => ['description' => 'A routine professional cleaning to remove plaque and tartar deposits and polish the teeth.', 'duration_minutes' => 30],
            'Dental Filling' => ['description' => 'A tooth-colored or other suitable filling used to restore a tooth affected by decay or minor damage.', 'duration_minutes' => 40],
            'Dental X-Ray' => ['description' => 'A diagnostic dental X-ray selected by the dentist to examine teeth and nearby structures.', 'duration_minutes' => 10],
            'Tooth Extraction' => ['description' => 'Removal of a tooth when the dentist determines it cannot or should not be retained.', 'duration_minutes' => 30],
            'Teeth Whitening' => ['description' => 'Professional whitening of natural teeth after the dentist checks that treatment is suitable.', 'duration_minutes' => 90],
        ];

        foreach ($referenceServices as [$name, $category, $priceDisplay]) {
            $service = Service::query()
                ->whereNull('branch_id')
                ->firstOrNew(['name' => $name]);

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
            ->whereNull('branch_id')
            ->whereIn('name', array_keys($serviceDetails))
            ->get()
            ->each(function (Service $service) use ($serviceDetails): void {
                if (blank($service->description)) {
                    $service->description = $serviceDetails[$service->name]['description'];
                    $service->duration_minutes = $serviceDetails[$service->name]['duration_minutes'];
                    $service->save();
                }
            });

        Service::query()
            ->whereNotNull('branch_id')
            ->whereNotNull('base_service_id')
            ->with('baseService')
            ->get()
            ->each(function (Service $service): void {
                if (blank($service->description) && $service->baseService !== null) {
                    $service->description = $service->baseService->description;
                    $service->duration_minutes = $service->baseService->duration_minutes;
                    $service->save();
                }
            });

        Service::query()
            ->whereIn('name', ['Dental Cleaning', 'Tooth Extraction', 'Dental Filling', 'Root Canal', 'Dental X-Ray', 'Teeth Whitening'])
            ->whereNull('price_display')
            ->update(['is_active' => false]);
    }
}
