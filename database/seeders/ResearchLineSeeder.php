<?php

namespace Database\Seeders;

use App\Models\ResearchLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResearchLineSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private const LINES_BY_PROGRAM = [
        'Maestría en Ciencias de la Ingeniería' => [
            'LGAC-2025-CELA-MCING-01' => 'Tópicos Avanzados de Ingeniería de Software',
            'LGAC-2025-CELA-MCING-02' => 'Tecnologías avanzadas para la Industria 4.0',
            'LGAC-2025-CELA-MCING-03' => 'Agricultura Inteligente',
        ],
        'Maestría en Ciencias en Ingeniería Bioquímica' => [
            'LGAC-2025-CELA-MCIBQ-04' => 'Bioingeniería',
            'LGAC-2025-CELA-MCIBQ-05' => 'Biología Molecular',
            'LGAC-2025-CELA-MCIBQ-06' => 'Ciencia y Tecnología de Alimentos',
        ],
        'Maestría en Ciencias en Ingeniería Electrónica' => [
            'LGAC-2025-CELA-MCIEO-07' => 'Calidad de la Energía',
            'LGAC-2025-CELA-MCIEO-08' => 'Fuentes Renovables con Control Inteligente',
            'LGAC-2025-CELA-MCIEO-09' => 'Sistemas de Adquisición y Procesamiento Digital de Señales',
        ],
        'Maestría en Ciencias en Ingeniería Mecánica' => [
            'LGAC-2025-CELA-MCIME-10' => 'Dinámica y Sistemas de Control',
            'LGAC-2025-CELA-MCIME-11' => 'Diseño Mecánico',
            'LGAC-2025-CELA-MCIME-12' => 'Ingeniería de Materiales',
        ],
        'Maestría en Ciencias en Ingeniería Química' => [
            'LGAC-2025-CELA-MCIQU-13' => 'Ciencia Básica en Ingeniería Química',
            'LGAC-2025-CELA-MCIQU-14' => 'Ingeniería de Procesos',
            'LGAC-2025-CELA-MCIQU-15' => 'Nuevas Tecnologías para el Desarrollo Sustentable',
        ],
        'Maestría en Gestión Administrativa' => [
            'LGAC-2025-CELA-MPGAD-16' => 'Estudios Sobre las Mipymes',
            'LGAC-2025-CELA-MPGAD-17' => 'Gestión Organizacional',
        ],
        'Maestría en Ingeniería Industrial' => [
            'LGAC-2025-CELA-MPIIN-18' => 'Administración de las Operaciones Industriales',
            'LGAC-2025-CELA-MPIIN-19' => 'Diseño y Mejora de Procesos y Productos',
            'LGAC-2025-CELA-MPIIN-20' => 'Estadística Industrial Aplicada',
        ],
        'Maestría en Ingeniería Mecatrónica' => [
            'LGAC-2025-CELA-MPIMC-21' => 'Control Digital, Robótica y Automatización',
            'LGAC-2025-CELA-MPIMC-22' => 'Diseño de Sistemas Mecatrónicos',
        ],
        'Maestría en Innovación Aplicada' => [
            'LGAC-2025-CELA-MPIA-23' => 'Innovación Aplicada y Sustentable en Industrial del Sector Agrícola-Alimentos',
            'LGAC-2025-CELA-MPIA-24' => 'Innovación en Industrias del Sector Automotriz y Metal-Mecánico',
            'LGAC-2025-CELA-MPIA-25' => 'Innovación Tecnológica',
            'LGAC-2025-CELA-MPIA-26' => 'Innovación Social',
        ],
        'Doctorado en Ciencias de la Ingeniería' => [
            'LGAC-2025-CELA-DING-27' => 'Desarrollo de Bioprocesos',
            'LGAC-2025-CELA-DING-28' => 'Materiales Avanzados',
            'LGAC-2025-CELA-DING-29' => 'Optimización y Mejora de Sistemas Industriales',
            'LGAC-2025-CELA-DING-30' => 'Tecnologías Electrónicas',
        ],
        'Doctorado en Ciencias en Ingeniería Bioquímica' => [
            'LGAC-2025-CELA-DIBQ-31' => 'Bioingeniería',
            'LGAC-2025-CELA-DIBQ-32' => 'Biología Molecular',
            'LGAC-2025-CELA-DIBQ-33' => 'Ciencia y Tecnología de Alimentos',
        ],
        'Doctorado en Ciencias en Ingeniería Electrónica' => [
            'LGAC-2025-CELA-DIEO-34' => 'Autotrónica e Inteligencia Artificial',
            'LGAC-2025-CELA-DIEO-35' => 'Bioelectrónica',
            'LGAC-2025-CELA-DIEO-36' => 'Energía Renovable y Eficiencia Energética',
        ],
        'Doctorado en Ciencias en Ingeniería Química' => [
            'LGAC-2025-CELA-DIQU-37' => 'Ciencia Básica en Ingeniería Química',
            'LGAC-2025-CELA-DIQU-38' => 'Ingeniería de Procesos',
            'LGAC-2025-CELA-DIQU-39' => 'Nuevas Tecnologías para el Desarrollo Sustentable',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::LINES_BY_PROGRAM as $program => $lines) {
                foreach ($lines as $code => $name) {
                    if (! ResearchLine::where('code', $code)->exists()) {
                        ResearchLine::firstOrCreate(['program' => $program, 'name' => $name], ['code' => $code]);
                    }
                }
            }
        });
    }
}
