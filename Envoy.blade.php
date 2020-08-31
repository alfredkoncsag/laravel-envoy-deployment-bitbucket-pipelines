@servers(['web' => $user.'@'.$host])

@setup

    if (empty($user)) {
        throw new Exception('ERROR: $user var empty or not defined (DEPLOY_USER)');
    }

    if (empty($host)) {
        throw new Exception('ERROR: $host var empty or not defined (DEPLOY_HOST)');
    }

    if (empty($path)) {
        throw new Exception('ERROR: $path var empty or not defined (DEPLOY_PATH)');
    }

    if (empty($repo)) {
        throw new Exception('ERROR: $repo var empty or not defined (BITBUCKET_REPO_FULL_NAME)');
	}

    if (empty($build)) {
        throw new Exception('ERROR: $build var empty or not defined (BITBUCKET_BUILD_NUMBER)');
    }

    if (empty($commit)) {
        throw new Exception('ERROR: $commit var empty or not defined (BITBUCKET_COMMIT)');
    }

    $env = isset($env) ? $env : "production";

    $branch = isset($branch) ? $branch : "master";

	$path = rtrim($path, '/');

	$buildNumber = $build . '-' . $commit;

    $release = $path.'/release-'. $buildNumber;
@endsetup

@task('init')
	if [ ! -d {{ $path }}/current ]; then

		echo "1. Open deploy foleder: cd {{ $path }}"
		cd {{ $path }}

		echo "2. Clone repository: git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}"
		git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
		echo "2.1. Repository cloned"

		echo "3. Move the firsth release storage folder to the project root: mv {{ $release }}/storage {{ $path }}/storage"
		mv {{ $release }}/storage {{ $path }}/storage

		echo "4. Create storage symlink: ln -s {{ $path }}/storage {{ $release }}/storage"
		ln -s {{ $path }}/storage {{ $release }}/storage

		echo "5. Create storage public folder symlink: ln -s {{ $path }}/storage/public {{ $release }}/public/storage"
		ln -s {{ $path }}/storage/public {{ $release }}/public/storage
		echo "5.1. Storage directory set up"

		echo "6. Move .env file to the project root: cp {{ $release }}/.env.example {{ $path }}/.env"
		cp {{ $release }}/.env.example {{ $path }}/.env

		echo "7. Create .env symlink: ln -s {{ $path }}/.env {{ $release }}/.env"
		ln -s {{ $path }}/.env {{ $release }}/.env
		echo "7.1. Environment file set up"

		echo "8. Delete init release folder: rm -rf {{ $release }}"
		rm -rf {{ $release }}

		echo "---------------------------------------------------------------------------------------------------------------------------"
		echo "Deployment path initialised. Run 'envoy run deploy' now."
	else
		echo "Deployment path already initialised (current symlink exists)!"
	fi
@endtask

@story('deploy')
	deploymentStart
	deploymentLinks
    deploymentNode
	deploymentComposer
	deploymentMigrate
	deploymentCache
	deploymentFinish
	healthCheck
	deploymentOptionCleanup
@endstory

@story('deployCleanup')
	deploymentStart
	deploymentLinks
    deploymentNode
	deploymentComposer
	deploymentMigrate
	deploymentCache
	deploymentFinish
	healthCheck
	deploymentCleanup
@endstory

@story('rollback')
	deploymentRollback
	healthCheck
@endstory

@task('deploymentStart')
	cd {{ $path }}
	echo "Deployment ({{ $buildNumber }}) started"
	git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
	echo "Repository cloned"
@endtask

@task('deploymentLinks')
	cd {{ $path }}
	rm -rf {{ $release }}/storage
	ln -s {{ $path }}/storage {{ $release }}/storage
	ln -s {{ $path }}/storage/public {{ $release }}/public/storage
	echo "Storage directories set up"
	ln -s {{ $path }}/.env {{ $release }}/.env
	echo "Environment file set up"
@endtask

@task('deploymentNode')
    echo "Installing npm depencencies..."
    cd {{ $release }}
	npm install
	npm run production
@endtask

@task('deploymentComposer')
	echo "Installing composer depencencies..."
	cd {{ $release }}
	composer install --no-interaction --quiet --no-dev --prefer-dist --optimize-autoloader
@endtask

@task('deploymentMigrate')
	php {{ $release }}/artisan migrate --env={{ $env }} --force --no-interaction
@endtask

@task('deploymentCache')
	php {{ $release }}/artisan view:clear --quiet
	php {{ $release }}/artisan cache:clear --quiet
	php {{ $release }}/artisan config:cache --quiet
	echo "Cache cleared"
@endtask

@task('deploymentFinish')
	php {{ $release }}/artisan queue:restart --quiet
	echo "Queue restarted"
	ln -nfs {{ $release }} {{ $path }}/current
	echo "Deployment ({{ $buildNumber }}) finished"
@endtask

@task('deploymentCleanup')
	cd {{ $path }}
	find . -maxdepth 1 -name "release-*" | sort | head -n -4 | xargs rm -Rf
	echo "Cleaned up old deployments"
@endtask

@task('deploymentOptionCleanup')
	cd {{ $path }}
	@if (isset($cleanup) && $cleanup)
		find . -maxdepth 1 -name "release-*" | sort | head -n -4 | xargs rm -Rf
		echo "Cleaned up old deployments"
	@endif
@endtask


@task('healthCheck')
	@if (!empty($healthUrl))
		if [ "$(curl --write-out "%{http_code}\n" --silent --output /dev/null {{ $healthUrl }})" == "200" ]; then
			printf "\033[0;32mHealth check to {{ $healthUrl }} OK\033[0m\n"
		else
			printf "\033[1;31mHealth check to {{ $healthUrl }} FAILED\033[0m\n"
		fi
	@else
		echo "No health check set"
	@endif
@endtask


@task('deploymentRollback')
	cd {{ $path }}
	ln -nfs {{ $path }}/$(find . -maxdepth 1 -name "release-*" | sort  | tail -n 2 | head -n1) {{ $path }}/current
	echo "Rolled back to $(find . -maxdepth 1 -name "release-*" | sort  | tail -n 2 | head -n1)"
@endtask
