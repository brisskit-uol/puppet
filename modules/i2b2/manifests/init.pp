class i2b2 { 
	
	class { '::ruby':
		gems_version  => 'latest',
	}
	
	class { '::apache': 
		default_vhost	=> false,
		mpm_module		=> 'prefork',
		require			=> Class['::ruby'],
	}
	
	::apache::vhost { 'i2b2':
		servername	=> 'default',
		docroot		=> '/var/www/html',
		port		=> '80',
		proxy_pass	=> [ { 'path' => '/i2b2UploaderWebapp', 'url' => 'http://localhost:9090/i2b2UploaderWebapp', }, ],
	}
	
	class { '::apache::mod::php': }
	
	package { 'php5-curl':
		ensure	=> present,
		require	=> Class['::apache::mod::php'],
	}
	
	file { [ '/var', '/var/local', '/var/local/brisskit', '/var/local/brisskit/i2b2', ]:
		ensure	=> directory,
	}
	
	file { '/var/local/brisskit/i2b2/i2b2-1.7-install-procedures':
		source	=> 'puppet:///modules/i2b2/i2b2-1.7-install-procedures',
		recurse	=> true,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/utility/startjboss.sh':
		mode	=> 'u+x',
		require	=> File['/var/local/brisskit/i2b2/i2b2-1.7-install-procedures'],
	}
	
	file { '/var/local/brisskit/i2b2/jboss-as-7.1.1.Final':
		source	=> 'puppet:///modules/i2b2/jboss-as-7.1.1.Final',
		recurse	=> true,
		replace	=> false,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/tmp/jdk1.7.0_17.tar.gz':
		source	=> 'puppet:///modules/i2b2/jdk1.7.0_17.tar.gz',
	}
	
	file { '/tmp/webclient.tar.gz':
		source	=> 'puppet:///modules/i2b2/webclient.tar.gz',
	}
	
	exec { "extract-jdk":
		command	=> "sudo tar -xf /tmp/jdk1.7.0_17.tar.gz -C /var/local/brisskit/i2b2/",
		cwd     => "/var/local/brisskit/i2b2",
		creates => "/var/local/brisskit/i2b2/jdk1.7.0_17",
		path    => [ '/bin', '/usr/bin', ],
		require	=> [ File['/var/local/brisskit/i2b2'], File['/tmp/jdk1.7.0_17.tar.gz'], ],
	}
	
	exec { "extract-webclient":
		command	=> "sudo tar -xf /tmp/webclient.tar.gz -C /var/www/html",
		cwd     => "/var/www/html",
		creates => "/var/www/html/i2b2",
		path    => [ '/bin', '/usr/bin', ],
		require	=> [ Class['::apache'], File['/tmp/webclient.tar.gz'], ],
	}
	
	file { '/var/local/brisskit/i2b2/jboss':
		ensure	=> link,
		target	=> 'jboss-as-7.1.1.Final',
		require	=> File['/var/local/brisskit/i2b2/jboss-as-7.1.1.Final'],
	}

	file { '/var/local/brisskit/i2b2/jboss/bin/standalone.sh':
		mode	=> 'u+x',
		require	=> File['/var/local/brisskit/i2b2/jboss'],
	}
		
	file { '/var/local/brisskit/i2b2/jdk':
		ensure	=> link,
		target	=> 'jdk1.7.0_17',
		require	=> Exec['extract-jdk'],
	}
	
	class { "postgresql::server": }
	
	postgresql::server::db { 'i2b2':
		user     => 'i2b2',
		password => 'i2b2',
	}
	
	postgresql::server::role { "i2b2":
		password_hash 	=> postgresql_password('i2b2', 'i2b2'),
		superuser		=> true,
	}
	
	postgresql::server::database_grant { "grant-i2b2":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2',
		require		=> Postgresql::Server::Role['i2b2'],
	}
		
	postgresql::server::role { "i2b2demodata":
		password_hash => postgresql_password('i2b2demodata', 'demouser'),
	}

	postgresql::server::database_grant { "grant-i2b2demodata":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2demodata',
		require		=> Postgresql::Server::Role['i2b2demodata'],
	}
		
	postgresql::server::role { "i2b2hive":
		password_hash => postgresql_password('i2b2hive', 'demouser'),
	}

	postgresql::server::database_grant { "grant-i2b2hive":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2hive',
		require		=> Postgresql::Server::Role['i2b2hive'],
	}
		
	postgresql::server::role { "i2b2metadata":
		password_hash => postgresql_password('i2b2metadata', 'demouser'),
	}

	postgresql::server::database_grant { "grant-i2b2metadata":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2metadata',
		require		=> Postgresql::Server::Role['i2b2metadata'],
	}
		
	postgresql::server::role { "i2b2pm":
		password_hash => postgresql_password('i2b2pm', 'demouser'),
	}

	postgresql::server::database_grant { "grant-i2b2pm":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2pm',
		require		=> Postgresql::Server::Role['i2b2pm'],
	}
	
	postgresql::server::role { "i2b2workdata":
		password_hash => postgresql_password('i2b2workdata', 'demouser'),
	}

	postgresql::server::database_grant { "grant-i2b2workdata":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2workdata',
		require		=> Postgresql::Server::Role['i2b2workdata'],
	}
		
	postgresql::server::role { "i2b2im":
		password_hash => postgresql_password('i2b2im', 'demouser'),
	}
	
	postgresql::server::database_grant { "grant-i2b2im":
		privilege	=> 'CONNECT',
		db			=> 'i2b2',
		role		=> 'i2b2im',
		require		=> Postgresql::Server::Role['i2b2im'],
	}
	
	file { '/tmp/i2b2-dump.sql':
		source	=> 'puppet:///modules/i2b2/i2b2-dump.sql',
		notify	=> Exec['initialdump'],
		require	=> Postgresql::Server::Db['i2b2'],
	}
	
	exec { 'initialdump':
		command		=> 'sudo -u postgres psql -d i2b2 -f /tmp/i2b2-dump.sql && touch /tmp/.dbimport',
		path		=> ['/bin', '/usr/bin'],
		refreshonly	=> true,
		creates		=> '/tmp/.dbimport',
	}
	
	exec { 'startjboss':
		command	=> 'bash -c "source /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/global/set.sh && sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/utility/startjboss.sh"',
		path	=> ['/usr/bin', '/bin',],
		unless	=> 'ps -o pid --no-headers --ppid $(ps -C standalone.sh -o pid --no-headers) 2>/dev/null',
		require	=> [ File['/var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/utility/startjboss.sh'], File['/var/local/brisskit/i2b2/jboss/bin/standalone.sh'], ],
	}
	
}