<?php
/**
 *
 * @package SupportToolkit
 * @copyright (c) 2012 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

/**
 * Support Toolkit tool
 *
 * An wrapper for STK tools, provides various common methods and holds the
 * actual tool object.
 */
class stk_toolbox_tool
{
	private $active;
	private $category;
	private $id;
	private $outdated;
	private $tool;

	/**
	 * Initialise tool object
	 *
	 * This is a wrapper object that contains a tool for the support toolkit,
	 * this method should be called when initialising a new tool. It handles
	 * some initial validation on the tool and returns the `stk_toolbox_tool`
	 * object for the requested tool
	 *
	 * @param SplFileInfo $path Path to the tool file, the correct class name is
	 *                          determined from here
	 * @return string|\static The `stk_toolbox_tool` object or a string when an
	 *                        error occured.
	 */
	static public function createTool(SplFileInfo $path)
	{
		// Determine the class name
		$category	= substr(strrchr($path->getPath(), '/'), 1);
		$file		= $path->getBasename('.php');
		$className	= "stktool_{$category}_{$file}";

		// Test whether the class name is correctly formatted
		if (!preg_match('#^stktool_[a-zA-Z]+_[a-zA-Z_]+$#', $className))
		{
			return 'TOOL_CLASSNAME_WRONG_FORMAT';
		}

		$rc = new ReflectionClass($className);

		// Must implement the interface
		if (false === ($rc->implementsInterface('stk_toolbox_toolInterface')))
		{
			return 'TOOL_CLASS_NOT_IMPLEMENTS_INTERFACE';
		}

		// Tool version check
		$vc = stk_toolbox_version_check::getInstance();
		$vcr = $vc->testVersion($category, $file);
		if ($vcr == stk_toolbox_version_check::TOOL_BLOCKING || $vcr == stk_toolbox_version_check::TOOL_DISABLED)
		{
			return ($vcr == stk_toolbox_version_check::TOOL_BLOCKING) ? 'TOOL_VERSION_BLOCKED' : 'TOOL_DISABLED';
		}
		$outdated = ($vcr != stk_toolbox_version_check::TOOL_OK) ? true : false;

		return new static($rc->newInstanceArgs(), $category, $file, $outdated);
	}

	final private function __construct(stk_toolbox_toolInterface $tool, $categoryName = '', $toolName = '', $outdated = false)
	{
		global $user;

		$this->active	= false;
		$this->category	= $categoryName;
		$this->id		= $toolName;
		$this->outdated	= $outdated;
		$this->tool		= $tool;

		// Include this tools language file
		// @todo figure out a better way to handle this when running the test suite
		if ($user instanceof user)
		{
			$user->stk_add_lang("tools/{$this->category}/{$this->id}");
		}
	}

	public function createOverview()
	{
		global $template, $user;

		$options = $this->tool->displayOptions();

		// Show outdated notice
		if ($this->outdated)
		{
			$template->assign_block_vars('notices', array(
				'TITLE'			=> $user->lang('TOOL_OUTDATED_TITLE'),
				'DESCRIPTION'	=> $user->lang('TOOL_OUTDATED_DESCRIPTION')
			));
		}

		// Various options
		if (is_string($options))
		{
			$this->createStringOverview($options);
		}
	}

	private function createStringOverview($options)
	{
		if (stk_includes_utilities::confirm_box(true))
		{
			// Run the tool
		}
		else
		{
			stk_includes_utilities::confirm_box(false, $options, '', 'confirm_body.html', $this->getToolURL());
		}
	}

	public function isActive()
	{
		return $this->active;
	}

	public function setActive($active = false)
	{
		$this->active = $active;
	}

	public function getID()
	{
		return $this->id;
	}

	public function getToolLanguageString()
	{
		return strtoupper("TOOL_{$this->category}_{$this->id}");
	}

	public function getTool()
	{
		return $this->tool;
	}

	public function getToolURL(array $params = array())
	{
		// Add cat/tool to the params
		$params['c'] = $this->category;
		$params['t'] = $this->id;

		return append_sid(STK_WEB_PATH . '/index.php', $params);
	}
}