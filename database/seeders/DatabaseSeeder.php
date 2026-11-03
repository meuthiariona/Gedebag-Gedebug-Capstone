<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Data awal (contoh). Setelah di-seed, SEMUA data dikelola dari panel admin (/admin),
 * tidak perlu mengubah kode.
 */
class DatabaseSeeder extends Seeder
{
    private function img(string $id, int $w = 900): string
    {
        return "https://images.unsplash.com/photo-{$id}?w={$w}&q=80&auto=format&fit=crop";
    }

    public function run(): void
    {
        // ---------- Pengaturan ----------
        foreach ([
            'site_name' => 'NEXA',
            'company_name' => 'PT NEXA Live Production & Equipment Ltd.',
            'tagline' => 'Industrial-grade event hardware, live stage production, and premier entertainment bookings on single frictionless rails.',
            'npwp' => '01.345.678.9-428.000',
            'company_address' => "Jl. Ir. H. Juanda No. 128, Dago\nBandung, Jawa Barat 40132, Indonesia",
            'support_email' => 'ops@nexaevents.io',
            'support_phone' => '+62 (21) 5092-8812',
            'whatsapp' => '6282198838172',
            'support_region' => 'Jakarta & Bali Regional Dispatch',
            'ops_center_name' => 'Bandung Operations Center',
            'ops_center_address' => 'Jl. Dr. Setiabudi No. 128, Bandung',
            'dispatch_hub' => 'Fleet Dispatch: Regional Hub Bandung (West Java) Online',
            'logistics_fee' => 200000,
            'dp_percent' => 50,
            'hold_hours' => 48,
            'bank_name' => 'BCA',
            'bank_account' => '8271-0812-3456-7890',
            'bank_holder' => 'PT NEXA Live Production',
            'invoice_terms' => 'All audio, stage illumination, and power distribution hardware supplied by NEXA Live Production are backed by regional equipment liability coverage and certified under SNI / ISO 9001:2015 production rig safety standards. Technical soundchecks must be conducted jointly between client technical staff and NEXA audio engineers prior to public hall admittance.',
            'certified_title' => 'Certified Sound & Lighting Technicians Included',
            'certified_text' => 'Every rental tier includes full transport logistics, rig check, and live audio engineer standby.',
            'packages_title' => 'Event Packages',
            'packages_subtitle' => 'Complete solutions for your event with sound, lighting, engineers, and talent bundled.',
            'cta_title' => 'Need a Custom Turnkey Setup?',
            'cta_text' => 'Admin-curated packages with certified technical crew, sound engineers, acoustic modeling, and logistics transport included for stadium or multi-day conventions.',
            'sla_text' => "Enterprise live production SLA backed by industrial insurance & redundancy protocol.\n\n• 100% Equipment Redundancy: Spare mixer, wireless mics & driver amps dispatched in chase vehicles.\n• 24/7 Dedicated Sound Engineer: Certified FOH specialist stationed throughout the operational schedule.\n• Transit & Venue Liability: Comprehensive gear liability covers transit damage and power surges up to Rp 500M.",
        ] as $k => $v) {
            Setting::put($k, $v);
        }

        // ---------- User ----------
        User::create(['name' => 'Admin NEXA', 'email' => 'admin@nexa.test', 'password' => 'password', 'role' => 'admin', 'phone' => '+62 812-0000-0000']);
        $customer = User::create([
            'name' => 'Yoas Immanuel', 'email' => 'customer@example.com', 'password' => 'password', 'role' => 'customer',
            'phone' => '+62 812-3456-7890', 'company' => 'PT Digital Synergy Indonesia', 'address' => 'Jl. Gatot Subroto No. 45, Bandung, Indonesia',
        ]);

        // ---------- Banner ----------
        HeroSlide::create([
            'eyebrow' => 'Sound • Lighting • Talent • Production', 'title_bold' => 'Professional Event', 'title_light' => 'Solutions',
            'subtitle' => 'High-grade hardware rentals and premier performance talent to curate unforgettable stadium, gala, and corporate experiences.',
            'image' => $this->img('1501612780327-45045538702b', 1600), 'button_text' => 'Browse Products', 'button_url' => '/products',
            'secondary_text' => 'Showreel 2026', 'secondary_url' => 'https://www.youtube.com', 'sort_order' => 1,
        ]);
        HeroSlide::create([
            'eyebrow' => 'Turnkey Packages', 'title_bold' => 'One Booking.', 'title_light' => 'Full Production.',
            'subtitle' => 'Sound, lighting, rigging, engineers and talent bundled into one fixed price — delivered & rigged on schedule.',
            'image' => $this->img('1522158637959-30385a09e0da', 1600), 'button_text' => 'Explore Packages', 'button_url' => '/packages', 'sort_order' => 2,
        ]);

        // ---------- Kategori & produk ----------
        $catalog = [
            ['Sound System', 'speaker', 'Professional audio hardware engineered for precision, punch, and coverage.', [
                ['Line Array 10"', 'd&b / L\'Acoustics / RCF Tour Rig', 1500000, 'hari', '1563841930606-67e2bce48b78', 8],
                ['Subwoofer 18"', 'RCF 8006-AS Dual 18', 1000000, 'hari', '1545454675-3531b543be5d', 10],
                ['Top Speaker 15"', 'JBL SRX / RCF ART 715', 500000, 'hari', '1524368535928-5b5e00ddc76b', 16],
                ['Digital Mixer', 'Yamaha TF1 16-Channel', 800000, 'hari', '1598488035139-bdbb2231ce04', 4],
            ]],
            ['Lighting', 'lightbulb', 'Create stunning concert dynamics and refined architectural ambiances.', [
                ['Moving Head Beam', '230W 7R Stage Fixture', 750000, 'hari', '1429514513361-8fa32282fd5f', 24],
                ['LED PAR', '54x3W RGBW Wash', 150000, 'hari', '1504704911898-68304a7d2807', 40],
                ['Follow Spot', 'LED 350W Daylight', 900000, 'hari', '1478147427282-58a87a120781', 4],
                ['Smoke Machine', '1500W DMX Haze Unit', 250000, 'hari', '1493225457124-a3eb161ffa5f', 6],
            ]],
            ['MC / Host', 'mic', 'Captivating presenters to command attention and orchestrate proceedings.', [
                ['MC Formal', 'Corporate / Seminar / Gala', 1500000, 'sesi', '1544531586-fde5298cdd40', 3],
                ['MC Bilingual', 'Indonesian / English Fluent', 2000000, 'sesi', '1556761175-5973dc0f32e7', 2],
                ['MC Casual', 'Gathering / Festival / Party', 1200000, 'sesi', '1516280440614-37939bbacd81', 3],
                ['MC Wedding', 'Reception & Holy Matrimony', 2000000, 'sesi', '1519741497674-611481863552', 2],
            ]],
            ['Live Band', 'guitar', 'Virtuosic ensembles spanning intimate acoustic duos to 7-piece showbands.', [
                ['Acoustic Band', '3-4 Musicians Unplugged', 3000000, 'sesi', '1510915361894-db8b60106cb1', 2],
                ['Full Band', '5-7 Personnel Electric', 5000000, 'sesi', '1516450360452-9312f5e86fc7', 2],
                ['Jazz Band', '4-6 Personnel Smooth Jazz', 4000000, 'sesi', '1415201364774-f6f0bb35f28f', 1],
                ['Top 40 Band', '5-7 Person All-Hits Ensemble', 5500000, 'sesi', '1460723237483-7a6dc9d0b212', 1],
            ]],
            ['DJ', 'disc-3', 'Turn up the energy with resident club artists and pro turntable gear.', [
                ['DJ Club Set', 'House / Techno / Open Format', 3000000, 'sesi', '1470225620780-dba8ba36b745', 2],
                ['DJ with MC', 'Party & Night Event Package', 4000000, 'sesi', '1492684223066-81342ee5ff30', 1],
                ['Female DJ', 'EDM / Melodic & Commercial', 3500000, 'sesi', '1574391884720-bbc3740c59d1', 1],
                ['DJ Controller', 'Pioneer XDJ-RX3 Standalone', 1000000, 'hari', '1594623930572-300a3011d9ae', 3],
            ]],
        ];
        foreach ($catalog as $ci => [$name, $icon, $desc, $products]) {
            $cat = Category::create(['name' => $name, 'slug' => Str::slug($name), 'icon' => $icon, 'description' => $desc, 'card_style' => 'photo', 'sort_order' => $ci + 1]);
            foreach ($products as $pi => [$pn, $sub, $price, $unit, $img, $stock]) {
                $cat->products()->create([
                    'name' => $pn, 'slug' => Str::slug($pn), 'subtitle' => $sub, 'price' => $price, 'unit' => $unit,
                    'image' => $this->img($img, 600), 'stock' => $stock, 'sort_order' => $pi + 1,
                    'description' => "$pn — $sub. Termasuk pengecekan teknis sebelum dispatch.",
                ]);
            }
        }
        Category::create(['name' => 'Stage & Rigging', 'slug' => 'stage-rigging', 'icon' => 'construction', 'description' => 'Truss, staging decks and certified rigging hardware.', 'card_style' => 'photo', 'sort_order' => 6]);

        // ---------- Kategori paket ----------
        $pcats = [];
        foreach ([['Corporate', 'building-2'], ['Concert & Festival', 'tent'], ['Private Gathering', 'users'], ['Wedding & Reception', 'sparkles'], ['Nightlife & DJ', 'disc-3'], ['Acoustic / Intimate', 'guitar']] as $i => [$n, $ic]) {
            $pcats[$n] = PackageCategory::create(['name' => $n, 'slug' => Str::slug($n), 'icon' => $ic, 'sort_order' => $i + 1]);
        }

        // ---------- Paket ----------
        $packages = [
            [
                'cat' => 'Corporate', 'name' => 'Corporate Event Package', 'badge' => 'Corporate & Conference', 'price' => 8000000, 'stock' => 3,
                'summary' => 'Sound System (Top + Sub) • Stage Lighting (8 PAR, 4 Moving) • 1 Bilingual MC • Wireless Mics',
                'description' => 'Comprehensive industrial-grade sound, architectural ambient lighting, and certified bilingual host tailored for executive conferences, seminars, annual general meetings, and corporate galas. Fully rigged and calibrated by NEXA on-site audio engineers.',
                'series_label' => 'Tier 2 Executive Series', 'tier' => 'Tier 2', 'tags' => ['Best Seller', 'Turnkey Solution', 'All-in-One'],
                'cap' => [250, 600], 'watts' => 12000, 'img' => '1587825140708-dfaf72ae4b04', 'featured' => true,
                'gallery' => ['1540575467063-178a50c2df87', '1561489396-888724a1543d', '1505373877841-8d25f7d46678'],
                'items' => [
                    ['hardware', 4, 'Top Speaker 15" Active', 'JBL SRX / RCF ART 715-A Mk4 System', 'Front of House'],
                    ['hardware', 2, 'Subwoofer 18" Dual Active', 'RCF SUB 8006-AS High-Output Low-End', 'Sub-Bass Array'],
                    ['hardware', 4, 'Moving Head Beam 230W 7R', 'Sharpy beam fixtures with custom gobos', 'FX Lighting'],
                    ['hardware', 8, 'LED PAR 54x3W RGBW', 'Stage ambient wash & corporate backdrop fill', 'Wash Lighting'],
                    ['hardware', 1, 'Digital Mixer Yamaha TF1', '16-Channel motorized faders, Dante ready', 'FOH Console'],
                    ['hardware', 4, 'Shure Wireless Handheld Mics', 'UHF Beta 58A capsules with fresh lithium cells', 'Wireless RF'],
                    ['hardware', 1, 'Rigging, Power Distrib & Crank Stands', 'Heavy-duty aluminum safety certified stands', 'Hardware'],
                    ['talent', 1, 'MC Formal Bilingual', 'Indonesian / English corporate host', 'Talent'],
                    ['talent', 2, 'Audio & Lighting Engineers', 'Certified FOH & lighting programmer', 'Crew'],
                    ['service', 1, 'Delivery & Pre-rigging', 'Enclosed freight, setup & teardown', 'Logistics'],
                ],
                'addons' => [
                    ['+2 Additional Top Speakers 15"', 'Wider coverage for deep conference rooms or side-wing fill', 1000000, 'event'],
                    ['+4 Stage Moving Head Lights', 'High-impact dynamic ceiling beams and award spotlighting', 1500000, 'event'],
                    ['+1 Dedicated Video / Presentation Switcher', 'Seamless HDMI PPT / Keynote switching with confidence monitor output', 1200000, 'event'],
                    ['+1 Acoustic Band 3-Piece', 'Vocalist, keyboard & acoustic guitar for gala dinner intermission', 3000000, 'sesi'],
                ],
            ],
            ['cat' => 'Concert & Festival', 'name' => 'Concert & Festival Package', 'badge' => 'Concert & Festival', 'price' => 25000000, 'stock' => 1,
                'summary' => '10-box Line Array Rig • 16 Moving Heads + Hazer • 7-pc Showband Backline • Sound Engineers',
                'series_label' => 'Tier 4 Arena Series', 'tier' => 'Tier 4', 'tags' => ['Full Rig'], 'cap' => [1000, 5000], 'watts' => 40000, 'img' => '1470229722913-7c0e2dbbafd3', 'featured' => true,
                'items' => [['hardware', 10, 'Line Array 10"', 'Flown tour rig with ground stack subs', 'Main PA'], ['hardware', 16, 'Moving Head Beam 230W', 'Full DMX show programming', 'FX Lighting'], ['hardware', 2, 'Hazer', 'DMX controlled atmosphere', 'Atmos'], ['talent', 4, 'Production Crew', 'FOH, monitor, lighting & stage manager', 'Crew']],
                'addons' => [['+LED Wall 3x2m', 'P3.9 outdoor LED panels with processor', 6000000, 'event']]],
            ['cat' => 'Private Gathering', 'name' => 'Small Event / Intimate Gathering', 'badge' => 'Intimate Gathering', 'price' => 3500000, 'stock' => 5,
                'summary' => '2 Top Speakers 15" • 4 Warm Wash LEDs • 1 Casual MC • 1 Shure Wireless Mic',
                'series_label' => 'Tier 1 Compact Series', 'tier' => 'Tier 1', 'tags' => ['Compact'], 'cap' => [30, 150], 'watts' => 3000, 'img' => '1517457373958-b7bdd4587205', 'featured' => true,
                'items' => [['hardware', 2, 'Top Speaker 15"', 'JBL SRX active', 'Main PA'], ['hardware', 4, 'LED PAR Warm Wash', 'Ambient uplight', 'Wash'], ['talent', 1, 'MC Casual', 'Gathering host', 'Talent']]],
            ['cat' => 'Wedding & Reception', 'name' => 'Wedding & Gala Reception', 'badge' => 'Wedding & Gala', 'price' => 12000000, 'stock' => 2,
                'summary' => 'Acoustic Trio Ensemble • Wedding MC Host • Ambient Architectural Lighting • Wireless Mics',
                'series_label' => 'Tier 3 Celebration Series', 'tier' => 'Tier 3', 'tags' => ['Romantic Setup'], 'cap' => [200, 800], 'watts' => 10000, 'img' => '1519167758481-83f550bb49b3', 'featured' => true,
                'items' => [['talent', 1, 'Acoustic Trio Ensemble', 'Vocal, guitar & keys', 'Entertainment'], ['talent', 1, 'MC Wedding', 'Reception & holy matrimony', 'Host'], ['hardware', 8, 'LED PAR 54x3W', 'Architectural uplight', 'Ambient'], ['hardware', 4, 'Wireless Mics', 'Shure UHF', 'RF']]],
            ['cat' => 'Nightlife & DJ', 'name' => 'DJ & Club Night Package', 'badge' => 'Nightlife & Party', 'price' => 6500000, 'stock' => 4,
                'summary' => 'Pioneer DJ XDJ-RX3 • Dual 18" Subwoofers • Moving Head Beam • 1 Professional DJ (3 hrs)',
                'series_label' => 'Tier 2 Party Series', 'tier' => 'Tier 2', 'tags' => ['Party'], 'cap' => [100, 400], 'watts' => 8000, 'img' => '1566737236500-c8ac43014a67',
                'items' => [['hardware', 1, 'Pioneer XDJ-RX3', 'Standalone DJ system', 'DJ Booth'], ['hardware', 2, 'Subwoofer 18"', 'Dual active', 'Low End'], ['talent', 1, 'DJ Club Set', '3 hour set', 'Talent']]],
            ['cat' => 'Corporate', 'name' => 'Seminar & Hybrid Conference', 'badge' => 'Conference', 'price' => 5200000, 'stock' => 3,
                'summary' => 'Digital Audio Mixer • 4 Gooseneck Mics • Keylight LED Studio • Live Audio Recording',
                'series_label' => 'Tier 2 Hybrid Series', 'tier' => 'Tier 2', 'tags' => ['Hybrid Ready'], 'cap' => [50, 300], 'watts' => 4000, 'img' => '1505373877841-8d25f7d46678',
                'items' => [['hardware', 1, 'Digital Mixer', 'Yamaha TF1 with multitrack recording', 'FOH'], ['hardware', 4, 'Gooseneck Mics', 'Conference podium & panel', 'Mics'], ['hardware', 2, 'Keylight LED Panel', 'Studio camera lighting', 'Video']]],
            ['cat' => 'Acoustic / Intimate', 'name' => 'Live Band Showcase Package', 'badge' => 'Live Music', 'price' => 9500000, 'stock' => 2,
                'summary' => 'Full 6-Piece Band • Drum Kit & Amps • Stage Monitors • Lighting Programmer',
                'series_label' => 'Tier 3 Showcase Series', 'tier' => 'Tier 3', 'tags' => ['Live Music'], 'cap' => [100, 500], 'watts' => 9000, 'img' => '1514320291840-2e0a9bf2a9ae',
                'items' => [['talent', 1, 'Full Band 6-piece', 'Pop, jazz & top 40', 'Entertainment'], ['hardware', 1, 'Backline Drum Kit & Amps', 'Complete backline', 'Backline'], ['hardware', 4, 'Stage Monitors', 'Wedge monitors', 'Monitor']]],
            ['cat' => 'Concert & Festival', 'name' => 'Outdoor Exhibition & Bazaar', 'badge' => 'Outdoor & Festival', 'price' => 4800000, 'stock' => 6,
                'summary' => 'Weatherproof Horn Speakers • Ambient Festoon & Flood Lights • Paging MC Mic',
                'series_label' => 'Tier 1 Outdoor Series', 'tier' => 'Tier 1', 'tags' => ['Weatherproof'], 'cap' => [200, 2000], 'watts' => 5000, 'img' => '1528605248644-14dd04022da1',
                'items' => [['hardware', 6, 'Weatherproof Horn Speakers', 'Zoned paging', 'PA'], ['hardware', 20, 'Festoon & Flood Lights', 'Ambient outdoor', 'Lighting']]],
        ];
        foreach ($packages as $i => $p) {
            $pkg = Package::create([
                'package_category_id' => $pcats[$p['cat']]->id, 'name' => $p['name'], 'slug' => Str::slug($p['name']), 'badge' => $p['badge'],
                'summary' => $p['summary'], 'description' => $p['description'] ?? $p['summary'].'. Termasuk kru, pengiriman, setup & sound engineer on-site.',
                'series_label' => $p['series_label'], 'tier' => $p['tier'], 'tags' => $p['tags'], 'capacity_min' => $p['cap'][0], 'capacity_max' => $p['cap'][1],
                'power_watts' => $p['watts'], 'price' => $p['price'], 'unit' => 'event', 'image' => $this->img($p['img'], 1200),
                'gallery' => array_map(fn ($g) => $this->img($g, 1200), $p['gallery'] ?? []),
                'stock' => $p['stock'], 'engineers' => 2, 'is_featured' => $p['featured'] ?? false, 'sort_order' => $i + 1,
            ]);
            foreach ($p['items'] ?? [] as $k => [$type, $qty, $name, $sub, $tag]) {
                $pkg->items()->create(compact('type', 'qty', 'name', 'tag') + ['subtitle' => $sub, 'sort_order' => $k]);
            }
            foreach ($p['addons'] ?? [] as $k => [$name, $desc, $price, $unit]) {
                $pkg->addons()->create(['name' => $name, 'description' => $desc, 'price' => $price, 'unit' => $unit, 'sort_order' => $k]);
            }
        }

        // ---------- Booking contoh ----------
        $start = Carbon::today()->addDays(10);
        $this->booking($customer, [
            'event_name' => 'Corporate Gathering & Product Launch', 'start_date' => $start, 'end_date' => $start->copy()->addDays(2),
            'venue_name' => 'Graha Telkom Convention Hall', 'venue_address' => 'Jl. Japati No. 1, Sadang Serang, Coblong, Kota Bandung, Jawa Barat 40133',
            'expected_pax' => 450, 'pic_name' => 'Yoas Immanuel', 'pic_phone' => '+62 812-3456-7890', 'delivery_point' => 'Dock B Rear Gate',
            'status' => 'confirmed', 'phase_title' => 'Warehouse Rigging Bay #2', 'phase_note' => 'Hardware QA diagnostics, DMX channel patch assignment & flight case sealing underway by Tech Lead Yandi.',
            'fleet_name' => 'Bandung Central Fleet #04', 'driver_name' => 'Rudi Hartono (Heavy Flatbed Rig C-12)',
            'site_instructions' => "Use loading dock B via rear gate.\nFreight elevator booked for 08:30 AM rig setup.\nAccess permit pre-submitted.",
            'crew_note' => 'Crew: Andi Prasetyo (Lead FOH), 2 Stage Hands assigned', 'inspection_label' => 'Rigging Inspected', 'discount' => 1200000,
        ], [
            ['product', 'Top Speaker JBL SRX 15" Active', 'Class-D bi-amplified 1000W stage monitor & front fill system', 'Sound System', 2, 3, 500000, '1524368535928-5b5e00ddc76b'],
            ['product', 'Subwoofer RCF 18" Dual Active', '2500W RMS high-excursion low-frequency reinforcement', 'Sound System', 2, 3, 1000000, '1545454675-3531b543be5d'],
            ['product', 'Moving Head Beam 230W 7R Stage Light', 'DMX-512 automated beam fixtures with motorized frost prism', 'Lighting', 4, 3, 100000, '1429514513361-8fa32282fd5f'],
            ['talent', 'MC Formal Bilingual (Indo / English)', 'Master of Ceremony for plenary corporate address & keynote reveal', 'Talent', 1, 1, 2000000, '1544531586-fde5298cdd40'],
            ['crew', 'Certified Sound & Light Engineers (2 Pax)', 'FOH engineer & lighting console programmer dedicated on-site', 'Crew Support', 2, 3, 0, null],
            ['service', 'Logistics, Rigging & Delivery Fee (Bandung Metro)', 'Enclosed freight transport, stage rigging setup & post-event teardown', 'Freight', 1, 1, 200000, null],
        ], 3, 0.5, 'QRIS');

        $s2 = Carbon::today()->addDays(26);
        $this->booking($customer, [
            'event_name' => 'Private Acoustic Wedding Reception', 'start_date' => $s2, 'end_date' => $s2->copy()->addDay(),
            'venue_name' => 'The Gaia Hotel, Bandung', 'venue_note' => 'Mountain Terrace Ballroom', 'venue_address' => 'Jl. Dr. Setiabudi No. 430, Bandung',
            'pic_name' => 'Yoas Immanuel', 'pic_phone' => '+62 812-3456-7890', 'status' => 'confirmed', 'inspection_label' => 'Rider Approved',
            'crew_note' => 'Includes wireless in-ear monitors & sound technician soundcheck at 14:00 WIB',
        ], [
            ['package', 'Wedding & Gala Reception', 'Acoustic Trio Ensemble, LED PAR 54x3W (x8), Wireless Mics (x4)', 'Package', 1, 1, 11800000, '1519167758481-83f550bb49b3'],
            ['service', 'Logistics, Rigging & Delivery Fee', 'Bandung Metro', 'Freight', 1, 1, 200000, null],
        ], 2, 1.0, 'Virtual Account');

        $s3 = Carbon::today()->addDays(40);
        $this->booking($customer, [
            'event_name' => 'Year-End Corporate Gala Dinner', 'start_date' => $s3, 'end_date' => $s3->copy()->addDays(2),
            'venue_name' => 'Trans Luxury Hotel, Bandung', 'venue_note' => 'Grand Ballroom Main Rig', 'venue_address' => 'Jl. Gatot Subroto No. 289, Bandung',
            'pic_name' => 'Yoas Immanuel', 'pic_phone' => '+62 812-3456-7890', 'status' => 'pending', 'hold_expires_at' => now()->addHours(32),
            'phase_title' => 'Menunggu pembayaran DP',
        ], [
            ['package', 'Corporate Event Package', 'Turnkey Corporate Package, Digital Mixer Yamaha TF1, On-site Sound Engineer', 'Package', 1, 1, 8000000, '1587825140708-dfaf72ae4b04'],
            ['service', 'Logistics, Rigging & Delivery Fee', 'Bandung Metro', 'Freight', 1, 1, 200000, null],
        ], 0, 0, null);

        $s4 = Carbon::today()->subDays(30);
        $this->booking($customer, [
            'event_name' => 'Townhall Q3 2026', 'start_date' => $s4, 'end_date' => $s4->copy()->addDay(),
            'venue_name' => 'Telkom Landmark Tower', 'venue_address' => 'Jl. Gatot Subroto Kav. 52, Jakarta', 'pic_name' => 'Yoas Immanuel', 'pic_phone' => '+62 812-3456-7890',
            'status' => 'completed',
        ], [
            ['package', 'Seminar & Hybrid Conference', 'Hybrid conference setup', 'Package', 1, 1, 5200000, '1505373877841-8d25f7d46678'],
            ['service', 'Logistics, Rigging & Delivery Fee', 'Jakarta', 'Freight', 1, 1, 200000, null],
        ], 6, 1.0, 'Transfer Bank');
    }

    private function booking(User $user, array $data, array $items, int $stage, float $paidRatio, ?string $method): void
    {
        $booking = Booking::create($data + [
            'code' => Booking::nextCode(), 'access_token' => Str::random(40), 'user_id' => $user->id, 'dp_percent' => 50,
            'customer_name' => $user->name, 'customer_phone' => $user->phone, 'customer_email' => $user->email, 'customer_company' => $user->company,
        ]);
        foreach ($items as [$type, $name, $desc, $cat, $qty, $days, $rate, $img]) {
            $booking->items()->create([
                'item_type' => $type, 'name' => $name, 'description' => $desc, 'category' => $cat, 'qty' => $qty, 'days' => $days,
                'unit' => ['talent' => 'sesi', 'package' => 'event', 'service' => 'trip'][$type] ?? 'hari', 'rate' => $rate,
                'subtotal' => $rate * $qty * $days, 'image' => $img ? $this->img($img, 300) : null,
            ]);
        }
        $booking->recalc();
        $booking->advanceTo($stage);
        if ($paidRatio > 0) {
            $amount = (int) round($booking->total * $paidRatio);
            Payment::create(['booking_id' => $booking->id, 'amount' => $amount, 'method' => $method, 'reference' => 'QRS-'.rand(1000, 9999).'-'.strtoupper(Str::random(4)), 'status' => 'verified', 'paid_at' => now()->subDays(3)]);
            $booking->update(['amount_paid' => $amount]);
        }
    }
}
