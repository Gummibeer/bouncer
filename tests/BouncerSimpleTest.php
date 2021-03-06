<?php

class BouncerSimpleTest extends BaseTestCase
{
    public function test_bouncer_can_give_and_remove_abilities()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->allow($user)->to('edit-site');

        $this->assertTrue($bouncer->allows('edit-site'));

        $bouncer->disallow($user)->to('edit-site');

        $this->assertTrue($bouncer->denies('edit-site'));
    }

    public function test_bouncer_can_give_and_remove_roles()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->allow('admin')->to('edit-site');
        $bouncer->assign('admin')->to($user);

        $this->assertTrue($bouncer->allows('edit-site'));

        $bouncer->retract('admin')->from($user);

        $this->assertTrue($bouncer->denies('edit-site'));
    }

    public function test_bouncer_can_disallow_abilities_on_roles()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->allow('admin')->to('edit-site');
        $bouncer->disallow('admin')->to('edit-site');
        $bouncer->assign('admin')->to($user);

        $this->assertTrue($bouncer->denies('edit-site'));
    }

    public function test_bouncer_can_check_user_roles()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->assign('moderator')->to($user);
        $bouncer->assign('editor')->to($user);

        $this->assertTrue($bouncer->is($user)->a('moderator'));
        $this->assertTrue($bouncer->is($user)->an('editor'));
        $this->assertFalse($bouncer->is($user)->an('admin'));
    }

    public function test_bouncer_can_check_multiple_user_roles()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->assign('moderator')->to($user);
        $bouncer->assign('editor')->to($user);

        $this->assertTrue($bouncer->is($user)->a(['moderator', 'admin']));
        $this->assertTrue($bouncer->is($user)->an(['editor', 'moderator']));
        $this->assertTrue($bouncer->is($user)->all(['editor', 'moderator']));
        $this->assertFalse($bouncer->is($user)->all(['admin', 'moderator']));
    }
}
