<?php
namespace FTC\Discord\Db\Postgresql\Container;

use Psr\Container\ContainerInterface;
use FTC\Discord\Db\Postgresql\GuildMemberRepository as GuildMemberRepositoryImp;
use FTC\Discord\Db\Core;

class GuildMemberRepository
{
    
    public function __invoke(ContainerInterface $container)
    {
        $database = $container->get(Core::class);

        return new GuildMemberRepositoryImp($database);
    }
    
}
