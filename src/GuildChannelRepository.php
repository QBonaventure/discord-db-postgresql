<?php declare(strict_types=1);

namespace FTC\Discord\Db\Postgresql;

use FTC\Discord\Model\Aggregate\GuildChannelRepository as RepositoryInterface;
use FTC\Discord\Model\Aggregate\GuildChannel;
use FTC\Discord\Model\ValueObject\Snowflake\ChannelId;
use FTC\Discord\Model\ValueObject\Snowflake\GuildId;
use FTC\Discord\Model\Collection\GuildChannelCollection;
use FTC\Discord\Db\Postgresql\Mapper\GuildChannelMapper;
use FTC\Discord\Model\Collection\GuildChannelIdCollection;

class GuildChannelRepository extends PostgresqlRepository implements RepositoryInterface
{
    
    const SELECT_ALL = <<<'EOT'
SELECT id, name, position, type_id, category_id, permission_overwrite,
COALESCE(text_channels.topic, NULL) as topic,
COALESCE(voice_channels.bitrate, NULL) as bitrate,
COALESCE(voice_channels.user_limit, NULL) as user_limit
FROM guilds_channels
LEFT JOIN guilds_text_channels text_channels ON type_id = 0 AND text_channels.channel_id = id
LEFT JOIN guilds_voice_channels voice_channels ON type_id = 2 AND voice_channels.channel_id = id
WHERE guild_id = :guild_id
EOT;

    const SELECT_IDS = <<<'EOT'
SELECT id
FROM guilds_channels
WHERE guild_id = :guild_id
EOT;

    const DELETE_CHANNEL = <<<'EOT'
UPDATE guilds_channels
SET is_active = false
WHERE id = :id
EOT;

    const GET_BY_ID = <<<'EOT'
SELECT * FROM guilds_channel
WHERE id = :id
EOT;

    const INSERT_GUILD_CHANNEL = <<<'EOT'
INSERT INTO guilds_channels VALUES (:id, :guild_id, :name, :position, :type_id, :permission_overwrite, :category_id)
ON CONFLICT (id) DO UPDATE SET name = :name, position = :position, permission_overwrite = :permission_overwrite, category_id = :category_id;
EOT;
    
    const INSERT_TEXT_CHANNEL = <<<'EOT'
INSERT INTO guilds_text_channels VALUES (:channel_id, :topic)
ON CONFLICT (channel_id) DO UPDATE SET topic = :topic;
EOT;
    
    const INSERT_VOICE_CHANNEL = <<<'EOT'
INSERT INTO guilds_voice_channels VALUES (:channel_id, :bitrate, :user_limit)
ON CONFLICT (channel_id) DO UPDATE SET bitrate = :bitrate, user_limit = :user_limit;
EOT;
    
    /**
     * @var GuildChannel[]
     */
    private $channels;
    

    public function save(GuildChannel $channel, GuildId $guildId)
    {
        $this->persistence->beginTransaction();
        $stmt = $this->persistence->prepare(self::INSERT_GUILD_CHANNEL);
        $stmt->bindValue('id', $channel->getId()->get(), \PDO::PARAM_INT);
        $stmt->bindValue('guild_id', $guildId->get(), \PDO::PARAM_INT);
        $stmt->bindValue('name', $channel->getName(), \PDO::PARAM_INT);
        $stmt->bindValue('type_id', $channel->getTypeId(), \PDO::PARAM_INT);
        $stmt->bindValue('permission_overwrite', $channel->getPermissionOverwrites()->toJson(), \PDO::PARAM_STR);
        $stmt->bindValue('position', $channel->getPosition(), \PDO::PARAM_INT);
        $stmt->bindValue('category_id', (int) (string) $channel->getCategoryId(), \PDO::PARAM_INT);
        $stmt->execute();
        
        if ($channel->getTypeId() == 0 && $topic = $channel->getTopic()) {
            $stmt = $this->persistence->prepare(self::INSERT_TEXT_CHANNEL);
            $stmt->bindValue('channel_id', (int) (string) $channel->getId(), \PDO::PARAM_INT);
            $stmt->bindValue('topic', $topic, \PDO::PARAM_STR);
            $stmt->execute();
        }
        
        if ($channel->getTypeId() == 2) {
            $stmt = $this->persistence->prepare(self::INSERT_VOICE_CHANNEL);
            $stmt->bindValue('channel_id', (int) (string) $channel->getId(), \PDO::PARAM_INT);
            $stmt->bindValue('bitrate', $channel->getBitrate(), \PDO::PARAM_INT);
            $stmt->bindValue('user_limit', $channel->getUserLimit(), \PDO::PARAM_INT);
            $stmt->execute();
        }
        
        $this->persistence->commit();
    }
    
    public function delete(ChannelId $channelId) : bool
    {
        $stmt = $this->persistence->prepare(self::DELETE_CHANNEL);
        $stmt->bindValue('id', $channelId->get(), \PDO::PARAM_STR);
        
        return $stmt->execute();
    }
    
    public function findById(ChannelId $id) : ?GuildChannel
    {
        $stmt = $this->persistence->prepare(self::GET_BY_ID);
        $stmt->bindValue('id', $id->get(), PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAll(GuildId $guildId) : GuildChannelCollection
    {
        $stmt = $this->persistence->prepare(self::SELECT_ALL);
        $stmt->bindValue('guild_id', (int) (string) $guildId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $channels = array_map([GuildChannelMapper::class, 'create'], $data);
        return new GuildChannelCollection(...$channels);
    }
    
}
