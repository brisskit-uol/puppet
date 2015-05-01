class i2b2 {
	
	class { '::ruby':
		gems_version  => 'latest',
	}
	
	class { '::apache': 
		docroot		=> '/var/www/html',
		mpm_module	=> 'prefork',
		require		=> Class['::ruby'],
	}
	
	file { [ '/var', '/var/local', '/var/local/brisskit', '/var/local/brisskit/i2b2', ]:
		ensure	=> directory,
	}
	
	file { '/var/local/brisskit/i2b2/i2b2-1.7-install-procedures':
		source	=> 'puppet:///modules/i2b2/i2b2-1.7-install-procedures',
		recurse	=> true,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/var/local/brisskit/i2b2/jboss-as-7.1.1.Final':
		source	=> 'puppet:///modules/i2b2/jboss-as-7.1.1.Final',
		recurse	=> true,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/tmp/jdk1.7.0_17.tar.gz':
		source	=> 'puppet:///modules/i2b2/jdk1.7.0_17.tar.gz',
	}
	
	file { '/tmp/webclient.tar.gz':
		source	=> 'puppet:///modules/i2b2/webclient.tar.gz',
	}
	
	exec { "tar -xf /tmp/jdk1.7.0_17.tar.gz -C /var/local/brisskit/i2b2/":
		cwd     => "/var/local/brisskit/i2b2",
		creates => "/var/local/brisskit/i2b2/jdk1.7.0_17",
		path    => ["/usr/bin", "/usr/sbin"],
		require	=> [ File['/var/local/brisskit/i2b2'], File['/tmp/jdk1.7.0_17.tar.gz'], ],
	}
	
	exec { "tar -xf /tmp/webclient.tar.gz -C /var/www/html":
		cwd     => "/var/www/html",
		creates => "/var/www/html/i2b2",
		path    => ["/usr/bin", "/usr/sbin"],
		require	=> [ Class['::apache'], File['/tmp/webclient.tar.gz'], ],
	}

}