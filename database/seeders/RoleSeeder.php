<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesWithSkills = [
            [
                'code' => 'PM',
                'name' => 'Project Manager',
                'skills' => [
                    'Agile / Scrum Methodology',
                    'Sprint Planning & Backlog Management',
                    'Jira / Trello / Linear',
                    'Risk Management',
                    'Team Communication & Leadership',
                ],
            ],
            [
                'code' => 'UIUX',
                'name' => 'UI/UX Designer',
                'skills' => [
                    'Figma / UI Design System',
                    'Wireframing & Prototyping',
                    'User Journey & Persona Mapping',
                    'Usability Testing',
                    'Design Handoff',
                ],
            ],
            [
                'code' => 'FE',
                'name' => 'Front-End Developer',
                'skills' => [
                    'HTML5, CSS3, Tailwind CSS',
                    'JavaScript (ES6+) / TypeScript',
                    'React / Vue.js / Next.js',
                    'REST API Integration',
                    'Responsive Web Design',
                ],
            ],
            [
                'code' => 'BE',
                'name' => 'Back-End Developer',
                'skills' => [
                    'PHP / Laravel Framework',
                    'RESTful API Development',
                    'Relational Database & MySQL Schema Design',
                    'Authentication & JWT / Sanctum',
                    'Git & CI/CD Pipeline Basics',
                ],
            ],
        ];

        foreach ($rolesWithSkills as $data) {
            $role = Role::updateOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name']]
            );

            foreach ($data['skills'] as $skillName) {
                Skill::firstOrCreate([
                    'role_id' => $role->id,
                    'name' => $skillName,
                ]);
            }
        }
    }
}
