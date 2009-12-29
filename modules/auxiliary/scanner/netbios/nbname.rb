##
# $Id$
##

##
# This file is part of the Metasploit Framework and may be subject to
# redistribution and commercial restrictions. Please see the Metasploit
# Framework web site for more information on licensing and terms of use.
# http://metasploit.com/framework/
##


require 'msf/core'


class Metasploit3 < Msf::Auxiliary

	include Msf::Auxiliary::Report
	include Msf::Auxiliary::Scanner

	def initialize
		super(
			'Name'        => 'NetBIOS Information Discovery',
			'Version'     => '$Revision$',
			'Description' => 'Discover host information through NetBIOS',
			'Author'      => 'hdm',
			'License'     => MSF_LICENSE
		)

		register_options(
		[
			OptInt.new('BATCHSIZE', [true, 'The number of hosts to probe in each set', 256]),
			Opt::RPORT(137)
		], self.class)
	end


	# Define our batch size
	def run_batch_size
		datastore['BATCHSIZE'].to_i
	end

	def rport
		datastore['RPORT'].to_i
	end

	# Fingerprint a single host
	def run_batch(batch)


		print_status("Sending NetBIOS status requests to #{batch[0]}->#{batch[-1]} (#{batch.length} hosts)")

		@results = {}
		begin
			udp_sock = nil
			idx = 0

			# Create an unbound UDP socket
			udp_sock = Rex::Socket::Udp.create()

			batch.each do |ip|
				begin
					data = create_netbios_status(ip)
					udp_sock.sendto(data, ip, rport, 0)
				rescue ::Interrupt
					raise $!
				rescue ::Rex::HostUnreachable, ::Rex::ConnectionTimeout, ::Rex::ConnectionRefused
					nil
				end

				if (idx % 30 == 0)
					while (r = udp_sock.recvfrom(65535, 0.1) and r[1])
						parse_reply(r)
					end
				end

				idx += 1
			end

			while (r = udp_sock.recvfrom(65535, 3) and r[1])
				parse_reply(r)
			end

			# Second pass to find additional IPs per host name

			@results.keys.each do |ip|
				next if not @results[ip][:name]
				begin
					data = create_netbios_lookup(@results[ip][:name])
					udp_sock.sendto(data, ip, rport, 0)
				rescue ::Interrupt
					raise $!
				rescue ::Rex::HostUnreachable, ::Rex::ConnectionTimeout, ::Rex::ConnectionRefused
					nil
				end

				if (idx % 30 == 0)
					while (r = udp_sock.recvfrom(65535, 0.1) and r[1])
						parse_reply(r)
					end
				end

				idx += 1
			end

			while (r = udp_sock.recvfrom(65535, 3) and r[1])
				parse_reply(r)
			end

		rescue ::Interrupt
			raise $!
		rescue ::Exception => e
			print_status("Unknown error: #{e.class} #{e}")
		end

		@results.keys.each do |ip|
			host = @results[ip]
			user = ""
			os   = "Windows"

			if(host[:user] and host[:mac] != "00:00:00:00:00:00")
				user = " User:#{host[:user]}"
			end

			if(host[:mac] == "00:00:00:00:00:00")
				os = "Unix"
			end

			names = " Names:(" + host[:names].map{|n| n[0]}.uniq.join(", ") + ")"
			addrs = ""
			if(host[:addrs])
				addrs = "Addresses:(" + host[:addrs].map{|n| n[0]}.uniq.join(", ") + ")"
			end

			print_status("#{ip} [#{host[:name]}] OS:#{os}#{user}#{names} #{addrs} Mac:#{host[:mac]}")
		end
	end


	def parse_reply(pkt)
		# Ignore "empty" packets
		return if not pkt[1]

		addr = pkt[1]
		if(addr =~ /^::ffff:/)
			addr = addr.sub(/^::ffff:/, '')
		end

		data = pkt[0]

		head = data.slice!(0,12)

		xid, flags, quests, answers, auths, adds = head.unpack('n6')

		return if quests != 0
		return if answers == 0

		qname = data.slice!(0,34)
		rtype,rclass,rttl,rlen = data.slice!(0,10).unpack('nnNn')
		buff = data.slice!(0,rlen)

		names = []

		hname = nil
		uname = nil

		case rtype
		when 0x21
			rcnt = buff.slice!(0,1).unpack("C")[0]
			1.upto(rcnt) do
				tname = buff.slice!(0,15).gsub(/\x00.*/, '').strip
				ttype = buff.slice!(0,1).unpack("C")[0]
				tflag = buff.slice!(0,2).unpack('n')[0]
				names << [ tname, ttype, tflag ]
				hname = tname if ttype == 0x20
				uname = tname if ttype == 0x03
			end
			maddr = buff.slice!(0,6).unpack("C*").map{|c| "%.2x" % c }.join(":")

			@results[addr] = {
				:names => names,
				:mac   => maddr
			}

			if (!hname and @results[addr][:names].length > 0)
				@results[addr][:name] = @results[addr][:names][0][0]
			end

			@results[addr][:name] = hname if hname
			@results[addr][:user] = uname if uname

			inf = ''
			names.each do |name|
				inf << name[0]
				inf << ":<%.2x>" % name[1]
				if (name[2] & 0x8000 == 0)
					inf << ":U :"
				else
					inf << ":G :"
				end
			end
			inf << maddr

			report_service(
				:host  => addr,
				:host_mac  => (maddr and maddr != '00:00:00:00:00:00') ? maddr : nil,
				:host_name => (hname) ? hname.downcase : nil,
				:port  => pkt[2],
				:proto => 'udp',
				:name  => 'NetBIOS',
				:info  => inf
			)
		when 0x20
			1.upto(rlen / 6.0) do
				tflag = buff.slice!(0,2).unpack('n')[0]
				taddr = buff.slice!(0,4).unpack("C*").join(".")
				names << [ taddr, tflag ]
			end
			@results[addr][:addrs] = names
			names.each do |name|
				report_note(
					:host  => addr,
					:proto => 'NetBIOS',
					:port  => pkt[2],
					:type  => "netbios_interface",
					:data  => name[0]
				)
			end
		end
	end

	def create_netbios_status(ip)
		data =
		[rand(0xffff)].pack('n')+
		"\x00\x00\x00\x01\x00\x00\x00\x00"+
		"\x00\x00\x20\x43\x4b\x41\x41\x41"+
		"\x41\x41\x41\x41\x41\x41\x41\x41"+
		"\x41\x41\x41\x41\x41\x41\x41\x41"+
		"\x41\x41\x41\x41\x41\x41\x41\x41"+
		"\x41\x41\x41\x00\x00\x21\x00\x01"

		return data
	end

	def create_netbios_lookup(name)
		name = [name].pack("A15") + "\x00"

		data =
		[rand(0xffff)].pack('n') +
		"\x01\x00\x00\x01\x00\x00\x00\x00\x00\x00" +
		"\x20" +
		Rex::Proto::SMB::Utils.nbname_encode(name) +
		"\x00" +
		"\x00\x20\x00\x01"

		return data
	end
end
