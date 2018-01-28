<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

class AliasIP extends Command
{
    public function run(...$arguments): bool
    {
        $existingIPs = $this->determineAliases();
        foreach ($arguments as $ip) {
            if (!in_array($ip, $existingIPs)) {
                // Not found yet, add
                info("Registering IP alias ($ip)");
                $existingIPs[] = $ip;
                ($command = new Exec())->run(di('sudo') . ' ' . di('ifconfig') . ' ' . di('lo') .  ' ' . $ip . ' alias');
            }
        }

        return true;
    }

    public function determineAliases()
    {
        // Fetch all ipv4 IPs for the loopback interface (for now no IPv6 support, sorry)
        $output = (new Exec())->run(di('ifconfig') . ' ' . di('lo') . " | grep 'inet '");
        // Extract IPs from the text
        preg_match_all('/inet (.*) netmask/mi', $output, $ips);

        return $ips ? array_slice($ips[1], 1) : [];
    }
}