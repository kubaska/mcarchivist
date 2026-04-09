<?php

namespace App\API\Contracts;

interface ThirdPartyApiTest
{
    public function test_search();

    public function test_get_project();

    public function test_get_projects();

    public function test_get_project_versions();

    public function test_get_project_dependencies();

    public function test_get_project_dependants();

    public function test_get_version();

    public function test_get_versions();

    public function test_get_versions_from_hashes();

    public function test_get_version_files();

    public function test_get_version_dependencies();

    public function test_get_version_dependants();

    public function test_get_project_authors();

    public function test_get_categories();

    public function test_get_loaders();
}
