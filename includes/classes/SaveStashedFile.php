<?php


class PublishStashedFile {

	/** @var User */
	private $user;

	/** @var string */
	private $filekey;

	/** @var string */
	private $filename;

	/** @var string */
	private $privateDirectory;

	/** @var string|null */
	private $lastError;

	/** @var string|null */
	private $finalPath;

	/**
	 * @param User $user
	 * @param string $filekey
	 * @param string $filename
	 * @param string $privateDirectory
	 */
	public function __construct(
		User $user,
		string $filekey,
		string $filename,
		string $privateDirectory
	) {
		$this->user = $user;
		$this->filekey = $filekey;
		$this->filename = $filename;
		$this->privateDirectory = rtrim( $privateDirectory, '/' );
		$this->lastError = null;
		$this->finalPath = null;
	}

	/**
	 * Move the stashed file to a private folder
	 *
	 * @return bool True on success, false on failure
	 */
	public function publish(): bool {
		try {
			if ( !$this->user->isRegistered() ) {
				$this->lastError = "Could not load the author user from session.";
				return false;
			}

			// Get the stashed file path
			$stashPath = $this->getStashedFilePath();
			if ( !$stashPath ) {
				return false;
			}

			// Verify the file
			if ( !$this->verifyFile( $stashPath ) ) {
				return false;
			}

			// Move the file to private directory
			if ( !$this->moveToPrivateFolder( $stashPath ) ) {
				return false;
			}

			// Cleanup the stash
			$this->cleanupStash();

			return true;

		} catch ( \Exception $e ) {
			$this->lastError = get_class( $e ) . ": " . $e->getMessage();
			return false;
		}
	}

	/**
	 * Get the final file path in the private directory
	 *
	 * @return string|null
	 */
	public function getFinalPath(): ?string {
		return $this->finalPath;
	}

	/**
	 * Get the last error message
	 *
	 * @return string|null
	 */
	public function getLastError(): ?string {
		return $this->lastError;
	}

	/**
	 * Get the stashed file path from the upload stash
	 *
	 * @return string|null
	 */
	private function getStashedFilePath(): ?string {
		$upload = new UploadFromStash( $this->user );
		$upload->initialize( $this->filekey, $this->filename );

		// Get the local file from stash
		$file = $upload->getLocalFile();
		if ( !$file || !$file->exists() ) {
			$this->lastError = "Stashed file not found.";
			return null;
		}

		// Get the file system path
		$localRepo = $file->getRepo();
		if ( !$localRepo ) {
			$this->lastError = "Could not get repository for stashed file.";
			return null;
		}

		$stashPath = $localRepo->getLocalReference( $file->getTitle() );
		if ( !$stashPath || !$stashPath->exists() ) {
			$this->lastError = "Could not locate stashed file on disk.";
			return null;
		}

		return $stashPath->getPath();
	}

	/**
	 * Verify the file
	 *
	 * @param string $filePath
	 * @return bool
	 */
	private function verifyFile( string $filePath ): bool {
		if ( !file_exists( $filePath ) || !is_file( $filePath ) ) {
			$this->lastError = "File does not exist at path: " . $filePath;
			return false;
		}

		// Check file size (optional)
		$maxSize = 100 * 1024 * 1024; // 100MB
		$fileSize = filesize( $filePath );
		if ( $fileSize === false || $fileSize > $maxSize ) {
			$this->lastError = "File size exceeds maximum allowed.";
			return false;
		}

		return true;
	}

	/**
	 * Move file to private folder
	 *
	 * @param string $sourcePath
	 * @return bool
	 */
	private function moveToPrivateFolder( string $sourcePath ): bool {
		// Ensure private directory exists
		if ( !is_dir( $this->privateDirectory ) ) {
			if ( !mkdir( $this->privateDirectory, 0755, true ) ) {
				$this->lastError = "Could not create private directory: " . $this->privateDirectory;
				return false;
			}
		}

		// Sanitize filename
		$safeFilename = $this->sanitizeFilename( $this->filename );
		
		// Handle duplicate filenames
		$targetPath = $this->getUniqueTargetPath( $this->privateDirectory, $safeFilename );

		// Move the file
		if ( !rename( $sourcePath, $targetPath ) ) {
			// Try copy + delete if rename fails (cross-device)
			if ( !copy( $sourcePath, $targetPath ) || !unlink( $sourcePath ) ) {
				$this->lastError = "Failed to move file to private directory.";
				return false;
			}
		}

		// Set permissions (private - owner only)
		chmod( $targetPath, 0600 );

		$this->finalPath = $targetPath;

		return true;
	}

	/**
	 * Get a unique target path (avoid overwriting existing files)
	 *
	 * @param string $directory
	 * @param string $filename
	 * @return string
	 */
	private function getUniqueTargetPath( string $directory, string $filename ): string {
		$path = $directory . '/' . $filename;
		
		// If file doesn't exist, return the path
		if ( !file_exists( $path ) ) {
			return $path;
		}

		// Add a counter to the filename
		$pathInfo = pathinfo( $filename );
		$name = $pathInfo['filename'];
		$ext = isset( $pathInfo['extension'] ) ? '.' . $pathInfo['extension'] : '';
		
		$counter = 1;
		while ( file_exists( $directory . '/' . $name . '-' . $counter . $ext ) ) {
			$counter++;
		}
		
		return $directory . '/' . $name . '-' . $counter . $ext;
	}

	/**
	 * Sanitize filename to prevent directory traversal
	 *
	 * @param string $filename
	 * @return string
	 */
	private function sanitizeFilename( string $filename ): string {
		// Remove any path components
		$filename = basename( $filename );
		
		// Remove any unwanted characters
		$filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
		
		// Ensure we have a valid filename
		if ( empty( $filename ) ) {
			$filename = 'file_' . uniqid();
		}
		
		return $filename;
	}

	/**
	 * Clean up the stashed file
	 */
	private function cleanupStash(): void {
		try {
			$upload = new UploadFromStash( $this->user );
			$upload->initialize( $this->filekey, $this->filename );
			$upload->cleanupTempFile();
		} catch ( \Exception $e ) {
			// Ignore cleanup errors - the file is already moved
		}
	}
}
